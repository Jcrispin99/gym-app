# Guía de Integración SUNAT (Greenter)

Este documento detalla los componentes necesarios para integrar la facturación electrónica con Greenter en tu proyecto Laravel.

## 1. Configuración de Entorno (.env)

Asegúrate de definir la URL de tu instancia de Greenter API:

```env
GREENTER_API_URL=http://greenter.test/api/
```

## 2. Modelos Requeridos

El servicio asume la existencia de los siguientes modelos con sus respectivas relaciones:

*   `Sale`: Venta principal using `App\Models\Sale`.
*   `Company`: Empresa emisora `App\Models\Company`.
*   `SunatConnection`: Credenciales (token) `App\Models\SunatConnection`.
*   `Journal`: Series y tipos de documento `App\Models\Journal`.
*   `PosOrder`, `PosConfig`, `PosSession`: Para lógica de punto de venta.
*   `Tax`: Configuración de impuestos.

## 3. Servicio: GreenterInvoiceService

Crea el archivo `app/Services/GreenterInvoiceService.php`:

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\Sale;
use App\Models\PosOrder;
use App\Models\PosConfig;
use App\Models\Company;
use App\Models\Tax;

class GreenterInvoiceService
{
    protected string $url;

    public function __construct()
    {
        $this->url = env('GREENTER_API_URL', 'http://greenter.test/api/');
    }

    protected function resolveTokenForSale(Sale $sale): string
    {
        $company = null;
        if (!empty($sale->company_id)) {
            $company = Company::query()->with('sunatConnection')->find($sale->company_id);
        }
        if (!$company) {
            $company = Company::query()->with('sunatConnection')->orderBy('id')->first();
        }
        $conn = $company?->sunatConnection;
        $token = (string) ($conn?->token_ikoodev ?? '');
        return trim($token);
    }

    public function buildPayloadFromSale(Sale $sale): array
    {
        $sale->loadMissing(['customer.identity', 'journal', 'variants.product', 'variants.attributeValues']);

        $docType = (string) ($sale->journal->document_type_code ?? '01');
        $isPos = !empty($sale->pos_order_id);
        $tax = $isPos ? $this->getTaxConfig($sale) : null;
        $totals = $this->buildDetailsAndTotalsPerLine($sale);
        $company = $this->buildCompanyPayload($sale, true);
        $client = $this->buildClientPayload($sale);

        $payload = [
            'tipoDoc' => $docType,
            'tipoOperacion' => '0101',
            'serie' => (string)($sale->serie ?? ''),
            'correlativo' => (string)($sale->correlative ?? ''),
            'fechaEmision' => ($sale->date ?? now())->setTimezone('America/Lima')->format('Y-m-d\TH:i:sP'),
            'formaPago' => ['moneda' => 'PEN', 'tipo' => 'Contado'],
            'tipoMoneda' => 'PEN',
            'company' => $company,
            'client' => $client,
            'details' => $totals['details'],
        ];

        if (in_array($docType, ['07', '08'], true)) {
            ['type' => $affectedType, 'number' => $affectedNumber] = $this->resolveAffectedDocument($sale);
            $payload['tipDocAfectado'] = $affectedType;
            $payload['numDocAfectado'] = $affectedNumber;
            $payload['numDocfectado'] = $affectedNumber;
            if ($docType === '07') {
                [$code, $label] = $this->resolveCreditNoteReason($sale);
                $payload['codMotivo'] = $code;
                $payload['desMotivo'] = $label;
            } else {
                $payload['codMotivo'] = '02';
                $payload['desMotivo'] = 'AUMENTO EN EL VALOR';
            }
        }

        return $payload;
    }

    private function resolveAffectedDocument(Sale $sale): array
    {
        $sale->loadMissing(['originalSale.journal']);
        $origin = $sale->originalSale ?: $sale;
        $guard = 0;
        while ($guard < 3) {
            $docType = (string) (optional($origin->journal)->document_type_code ?? '');
            if (in_array($docType, ['01', '03'], true)) break;
            if (! $origin->originalSale) break;
            $origin = $origin->originalSale;
            $guard++;
        }

        $type = (string) (optional($origin->journal)->document_type_code ?? '');
        if (! in_array($type, ['01', '03'], true)) {
            $serieGuess = (string) ($origin->serie ?? '');
            $type = str_starts_with($serieGuess, 'F') ? '01' : (str_starts_with($serieGuess, 'B') ? '03' : '01');
        }

        $number = '';
        $serie = (string) ($origin->serie ?? '');
        $corr = (string) ($origin->correlative ?? '');
        if ($serie !== '' && $corr !== '') {
            $number = $serie . '-' . $corr;
        } else {
            $origSerie = (string) ($sale->original_serie ?? '');
            $origCorr = (string) ($sale->original_correlative ?? '');
            if ($origSerie !== '' && $origCorr !== '') {
                $number = $origSerie . '-' . $origCorr;
            }
        }

        return ['type' => $type, 'number' => $number];
    }

    public function buildNotePayloadFromSale(Sale $sale, string $docType): array
    {
        $sale->loadMissing(['customer.identity', 'journal', 'variants.product', 'variants.attributeValues']);

        $tax = $this->getTaxConfig($sale);
        $totals = $this->buildDetailsAndTotals($sale, $tax);
        $company = $this->buildCompanyPayload($sale, true);
        $client = $this->buildClientPayload($sale);

        ['type' => $affectedType, 'number' => $affectedNumber] = $this->resolveAffectedDocument($sale);

        $payload = [
            'tipoDoc' => $docType,
            'serie' => (string) ($sale->serie ?? ''),
            'correlativo' => (string) ($sale->correlative ?? ''),
            'fechaEmision' => ($sale->date ?? now())->setTimezone('America/Lima')->format('Y-m-d\TH:i:sP'),
            'tipDocAfectado' => $affectedType,
            'numDocAfectado' => $affectedNumber,
            'numDocfectado' => $affectedNumber,
            'formaPago' => ['moneda' => 'PEN', 'tipo' => 'Contado'],
            'tipoMoneda' => 'PEN',
            'company' => $company,
            'client' => $client,
            'details' => $totals['details'],
            'mtoIGV' => $totals['igvTotal'],
            'totalImpuestos' => $totals['igvTotal'],
            'valorVenta' => $totals['baseTotal'],
            'subTotal' => $totals['subTotal'],
            'mtoImpVenta' => $totals['subTotal'],
        ];

        if ($docType === '07') {
            [$code, $label] = $this->resolveCreditNoteReason($sale);
            $payload['codMotivo'] = $code;
            $payload['desMotivo'] = $label;
        } else {
            $payload['codMotivo'] = '02';
            $payload['desMotivo'] = 'AUMENTO EN EL VALOR';
        }

        return $payload;
    }

    private function resolveCreditNoteReason(Sale $sale): array
    {
        try {
            $sale->loadMissing(['originalSale.variants']);
            $orig = $sale->originalSale;
            if (!$orig) return ['01', 'ANULACION DE LA OPERACION'];
            
            $origMap = [];
            foreach ($orig->variants as $v) $origMap[(string) $v->id] = (int) ($v->pivot->quantity ?? 0);
            
            $noteMap = [];
            foreach ($sale->variants as $v) $noteMap[(string) $v->id] = (int) ($v->pivot->quantity ?? 0);

            if (empty($origMap) || empty($noteMap)) return ['01', 'ANULACION DE LA OPERACION'];

            $isFull = (count($origMap) === count($noteMap));
            foreach ($origMap as $id => $qty) {
                if (!isset($noteMap[$id]) || $noteMap[$id] !== $qty) {
                    $isFull = false;
                    break;
                }
            }

            return $isFull ? ['06', 'DEVOLUCION TOTAL'] : ['07', 'DEVOLUCION POR ITEM'];
        } catch (\Throwable $e) {
            return ['01', 'ANULACION DE LA OPERACION'];
        }
    }

    public function sendInvoiceFromSale(Sale $sale): bool
    {
        try {
            $journal = $sale->journal;
            $isFiscal = (bool) (optional($journal)->is_fiscal ?? false);
            $docType = (string) (optional($journal)->document_type_code ?? '');
            $validDoc = in_array($docType, ['01', '03', '07', '08'], true);
            
            if (! $isFiscal || ! $validDoc) {
                // Lógica de skip (no enviar)
                try {
                    $sale->sunat_status = 'skipped';
                    $sale->sunat_response = [
                         'http_status' => null, 
                         'accepted' => false, 
                         'error' => $isFiscal ? 'Tipo Doc no soportado' : 'No fiscal', 
                         'updated_at' => now()->toIso8601String()
                    ];
                    $sale->save();
                } catch (\Throwable $e) {}
                return true;
            }

            // Evitar reenvío
            $alreadyAccepted = (bool) (data_get($sale->sunat_response, 'accepted') === true || ($sale->sunat_status === 'accepted'));
            if ($alreadyAccepted) return true;

            $dynamicToken = $this->resolveTokenForSale($sale);
            if (empty($dynamicToken)) {
                 // Log error de token
                 return true;
            }

            $sale->sunat_status = 'processing';
            $sale->save();

            $payload = in_array($docType, ['07', '08'], true)
                ? $this->buildNotePayloadFromSale($sale, $docType)
                : $this->buildPayloadFromSale($sale);

            $outPayload = $payload;
            unset($outPayload['items'], $outPayload['meta']);

            // Debug logs...
            
            $base = rtrim((string) $this->url, '/') . '/';
            $endpoint = in_array($docType, ['07', '08'], true) ? 'notes/send' : 'invoices/send';
            
            $response = Http::withToken($dynamicToken)->acceptJson()->post($base . $endpoint, $outPayload);

            if ($response->successful()) {
                $json = $response->json();
                $accepted = (bool) (data_get($json, 'body.response.SunatResponse.success') === true
                        || (int) data_get($json, 'cdrResponse.code') === 0
                        || (int) data_get($json, 'data.cdrResponse.code') === 0);
                
                $sale->sunat_status = $accepted ? 'accepted' : 'sent';
                $sale->sunat_response = [
                    'http_status' => $response->status(),
                    'accepted' => $accepted,
                    'cdr_code' => data_get($json, 'cdrResponse.code'),
                    'document_id' => data_get($json, 'response.document_id'),
                    'hash' => data_get($json, 'response.hash'),
                    'updated_at' => now()->toIso8601String(),
                ];
                $sale->save();
                return true;
            }

            $sale->sunat_status = 'error';
            $sale->sunat_response = ['http_status' => $response->status(), 'accepted' => false, 'error' => $response->body()];
            $sale->save();
            return false;
        } catch (\Throwable $e) {
            $sale->sunat_status = 'error';
            $sale->save();
            return false;
        }
    }

    // Métodos auxiliares (mapIdentityNameToTipoDoc, getTaxConfig, buildDetailsAndTotalsPerLine, buildCompanyPayload, etc...)
    // (Ver archivo original para implementación completa de estos métodos privados)
    
    private function mapIdentityNameToTipoDoc(?string $name): string
    {
        $n = mb_strtolower(trim((string)($name ?? '')));
        return match ($n) {
            'dni' => '1',
            'ruc' => '6',
            'carnet de extranjería', 'ce', 'carnet de extranjeria' => '4',
            'pasaporte' => '7',
            default => '0',
        };
    }
    
    // ... incluir resto de métodos privados aquí ...
    // (Para brevedad del ejemplo, asegúrate de copiar buildDetailsAndTotalsPerLine, getTaxConfig, etc. del código fuente original)
}
```

> **Nota**: El código anterior ha sido resumido. Asegúrate de incluir todos los métodos privados (`getTaxConfig`, `buildDetailsAndTotalsPerLine`, etc.) del archivo original.

## 4. Job: SendSunatInvoice

Este Job maneja el envío asíncrono. Crea el archivo `app/Jobs/SendSunatInvoice.php`:

```php
<?php

namespace App\Jobs;

use App\Models\Sale;
use App\Services\GreenterInvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendSunatInvoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $saleId;

    public function __construct(int $saleId)
    {
        $this->saleId = $saleId;
    }

    public function handle(GreenterInvoiceService $service): void
    {
        try {
            $sale = Sale::with('journal')->find($this->saleId);

            if (!$sale) {
                Log::warning('SendSunatInvoice: sale not found', ['sale_id' => $this->saleId]);
                return;
            }

            $service->sendInvoiceFromSale($sale);
        } catch (\Throwable $e) {
            Log::error('SendSunatInvoice failed', [
                'sale_id' => $this->saleId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
```

## 5. Uso

Para enviar un comprobante, despacha el Job después de crear la venta, idealmente usar `afterCommit()` para asegurar que la transacción de DB haya finalizado.

```php
use App\Jobs\SendSunatInvoice;

// ... código de creación de venta ...
$sale->save();

// Despachar a la cola
SendSunatInvoice::dispatch($sale->id)->afterCommit();
```
