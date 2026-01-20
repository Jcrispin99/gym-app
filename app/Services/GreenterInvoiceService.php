<?php

namespace App\Services;

use App\Models\Sale;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class GreenterInvoiceService
{
    private string $baseUrl;
    private string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) env('GREENTER_API_URL', ''), '/') . '/';
        $this->token = trim((string) env('GREENTER_API_TOKEN', ''));
    }

    public function sendInvoiceFromSale(Sale $sale): bool
    {
        $sale->loadMissing([
            'journal',
            'company',
            'partner',
            'products.tax',
            'products.productProduct.product',
            'products.productProduct.attributeValues.attribute',
        ]);

        $journal = $sale->journal;
        $docType = (string) ($journal->document_type_code ?? '');
        $isFiscal = (bool) ($journal->is_fiscal ?? false);

        if (! $isFiscal || ! in_array($docType, ['01', '03'], true)) {
            $sale->sunat_status = 'skipped';
            $sale->sunat_response = [
                'accepted' => false,
                'error' => $isFiscal ? 'Tipo de documento no soportado' : 'No fiscal',
                'updated_at' => now()->toIso8601String(),
            ];
            $sale->save();
            return true;
        }

        $alreadyAccepted = (bool) (data_get($sale->sunat_response, 'accepted') === true || $sale->sunat_status === 'accepted');
        if ($alreadyAccepted) {
            return true;
        }

        if ($this->baseUrl === '' || $this->token === '') {
            $sale->sunat_status = 'error';
            $sale->sunat_response = [
                'accepted' => false,
                'error' => 'Falta configuración GREENTER_API_URL o GREENTER_API_TOKEN',
                'updated_at' => now()->toIso8601String(),
            ];
            $sale->save();
            return false;
        }

        $sale->sunat_status = 'processing';
        $sale->save();

        $payload = $this->buildPayloadFromSale($sale);

        try {
            /** @var Response $response */
            $response = Http::withToken($this->token)
                ->acceptJson()
                ->post($this->baseUrl . 'invoices/send', $payload);

            $json = $response->json();
            $accepted = false;
            if ($response->successful()) {
                $successFlag = data_get($json, 'body.response.SunatResponse.success');
                $cdrCode = data_get($json, 'cdrResponse.code');
                $cdrCodeAlt = data_get($json, 'data.cdrResponse.code');

                $accepted = $successFlag === true
                    || (is_numeric($cdrCode) && (int) $cdrCode === 0)
                    || (is_numeric($cdrCodeAlt) && (int) $cdrCodeAlt === 0);
            }

            $sale->sunat_status = $accepted ? 'accepted' : ($response->successful() ? 'sent' : 'error');
            $sale->sunat_response = [
                'http_status' => $response->status(),
                'accepted' => $accepted,
                'cdr_code' => data_get($json, 'cdrResponse.code') ?? data_get($json, 'data.cdrResponse.code'),
                'hash' => data_get($json, 'response.hash') ?? data_get($json, 'data.hash'),
                'document_id' => data_get($json, 'response.document_id') ?? data_get($json, 'data.document_id'),
                'error' => $response->successful() ? null : $response->body(),
                'updated_at' => now()->toIso8601String(),
            ];
            $sale->sunat_sent_at = now();
            $sale->save();

            return $response->successful();
        } catch (\Throwable $e) {
            $sale->sunat_status = 'error';
            $sale->sunat_response = [
                'accepted' => false,
                'error' => $e->getMessage(),
                'updated_at' => now()->toIso8601String(),
            ];
            $sale->save();
            return false;
        }
    }

    public function buildPayloadFromSale(Sale $sale): array
    {
        $sale->loadMissing([
            'journal',
            'company',
            'partner',
            'products.tax',
            'products.productProduct.product',
            'products.productProduct.attributeValues.attribute',
        ]);

        $journal = $sale->journal;
        $docType = (string) ($journal->document_type_code ?? '01');

        $details = [];

        foreach ($sale->products as $line) {
            $qty = (float) ($line->quantity ?? 0);
            $base = (float) ($line->subtotal ?? 0);
            $igv = (float) ($line->tax_amount ?? 0);
            $rate = (float) ($line->tax_rate ?? 0);
            $unitBase = $qty > 0 ? ($base / $qty) : (float) ($line->price ?? 0);
            $unitWithTax = $unitBase * (1 + ($rate / 100));

            $tax = $line->tax;
            $tipAfeIgv = (string) ($tax?->affectation_type_code ?: ($rate > 0 ? '10' : '20'));
            $isGratuito = $tipAfeIgv === '21';

            $productProduct = $line->productProduct;
            $description = $productProduct?->display_name ?? 'Producto';
            $sku = $productProduct?->sku;
            $barcode = $productProduct?->barcode;

            $lineDetail = [
                'codProducto' => (string) ($barcode ?: ($sku ?: $line->id)),
                'unidad' => 'NIU',
                'cantidad' => $qty,
                'descripcion' => $description,
                'mtoValorUnitario' => $isGratuito ? 0.0 : round($unitBase, 2),
                'mtoValorVenta' => $isGratuito ? 0.0 : round($base, 2),
                'mtoBaseIgv' => $isGratuito ? 0.0 : round($base, 2),
                'porcentajeIgv' => $isGratuito ? 0.0 : round($rate, 2),
                'igv' => $isGratuito ? 0.0 : round($igv, 2),
                'tipAfeIgv' => $tipAfeIgv,
                'totalImpuestos' => $isGratuito ? 0.0 : round($igv, 2),
                'mtoPrecioUnitario' => $isGratuito ? 0.0 : round($unitWithTax, 2),
            ];

            if ($isGratuito) {
                $lineDetail['mtoValorGratuito'] = round($unitWithTax, 2);
            }

            $details[] = $lineDetail;
        }

        $company = $sale->company;
        $partner = $sale->partner;

        $payload = [
            'tipoDoc' => $docType,
            'tipoOperacion' => '0101',
            'serie' => (string) ($sale->serie ?? ''),
            'correlativo' => (string) ($sale->correlative ?? ''),
            'fechaEmision' => ($sale->date ?? now())->setTimezone('America/Lima')->format('Y-m-d\TH:i:sP'),
            'formaPago' => [
                'moneda' => 'PEN',
                'tipo' => 'Contado',
            ],
            'tipoMoneda' => 'PEN',
            'company' => [
                'ruc' => (string) ($company?->ruc ?? ''),
                'razonSocial' => (string) ($company?->business_name ?? $company?->trade_name ?? ''),
                'nombreComercial' => (string) ($company?->trade_name ?? ''),
                'address' => [
                    'direccion' => (string) ($company?->address ?? ''),
                    'ubigueo' => (string) ($company?->ubigeo ?? ''),
                    'departamento' => (string) ($company?->department ?? ''),
                    'provincia' => (string) ($company?->province ?? ''),
                    'distrito' => (string) ($company?->district ?? ''),
                    'urbanizacion' => (string) ($company?->urbanization ?? ''),
                    'codLocal' => '0000',
                ],
            ],
            'client' => [
                'tipoDoc' => $this->mapPartnerDocType($partner?->document_type),
                'numDoc' => (string) ($partner?->document_number ?? ''),
                'rznSocial' => (string) ($partner?->display_name ?? 'Cliente'),
            ],
            'details' => $details,
        ];

        return $payload;
    }

    private function mapPartnerDocType(?string $name): string
    {
        $n = mb_strtolower(trim((string) ($name ?? '')));

        return match ($n) {
            'dni' => '1',
            'ruc' => '6',
            'carnet de extranjería', 'carnet de extranjeria', 'ce' => '4',
            'pasaporte' => '7',
            default => '0',
        };
    }
}
