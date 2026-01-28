<?php

namespace App\Services;

use App\Models\Journal;
use App\Models\PosSession;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleRefundService
{
    public function resolveCreditNoteJournalForCompany(int $companyId, string $originDocType, ?string $originJournalCode = null): ?Journal
    {
        $preferredPrefix = $originDocType === '03' ? 'BC' : 'FC';
        $derivedCode = $this->deriveCreditNoteCode($originDocType, $originJournalCode);

        $base = Journal::query()
            ->where('company_id', $companyId)
            ->where('type', 'sale')
            ->where('document_type_code', '07');

        if ($derivedCode) {
            $journal = (clone $base)->where('code', $derivedCode)->first();
            if ($journal) {
                return $journal;
            }
        }

        return (clone $base)->where('code', 'like', "{$preferredPrefix}%")->orderBy('code')->first()
            ?? $base->first();
    }

    public function resolveCreditNoteJournalForPos(PosSession $session, string $originDocType, ?string $originJournalCode = null): ?Journal
    {
        $preferredPrefix = $originDocType === '03' ? 'BC' : 'FC';
        $derivedCode = $this->deriveCreditNoteCode($originDocType, $originJournalCode);

        $posQuery = $session->posConfig
            ->journals()
            ->where('journals.document_type_code', '07')
            ->wherePivot('document_type', 'credit_note');

        if ($derivedCode) {
            $journal = (clone $posQuery)->where('journals.code', $derivedCode)->first();
            if ($journal) {
                return $journal;
            }
        }

        $journal = (clone $posQuery)->where('journals.code', 'like', "{$preferredPrefix}%")->orderBy('journals.code')->first();
        if ($journal) {
            return $journal;
        }

        $journal = (clone $posQuery)->orderBy('journals.code')->first();
        if ($journal) {
            return $journal;
        }

        $companyId = $session->posConfig->company_id;

        return Journal::query()
            ->where('company_id', $companyId)
            ->where('document_type_code', '07')
            ->where('code', 'like', "{$preferredPrefix}%")
            ->orderBy('code')
            ->first()
            ?? Journal::query()
            ->where('company_id', $companyId)
            ->where('document_type_code', '07')
            ->orderBy('code')
            ->first();
    }

    public function originalQtyByProduct(Sale $origin): array
    {
        $origin->loadMissing('products');

        $out = [];
        foreach ($origin->products as $line) {
            $pid = (int) $line->product_product_id;
            $out[$pid] = ($out[$pid] ?? 0) + (float) $line->quantity;
        }

        return $out;
    }

    public function creditedQtyByProduct(int $originSaleId): array
    {
        return DB::table('productables')
            ->join('sales', 'sales.id', '=', 'productables.productable_id')
            ->where('productables.productable_type', Sale::class)
            ->where('sales.original_sale_id', $originSaleId)
            ->where('sales.status', 'posted')
            ->selectRaw('productables.product_product_id, SUM(productables.quantity) as qty')
            ->groupBy('productables.product_product_id')
            ->pluck('qty', 'productables.product_product_id')
            ->all();
    }

    public function availableQtyByProduct(Sale $origin): array
    {
        $original = $this->originalQtyByProduct($origin);
        $credited = $this->creditedQtyByProduct($origin->id);

        $available = [];
        foreach ($original as $pid => $qty) {
            $available[(int) $pid] = max(0, (float) $qty - (float) ($credited[$pid] ?? 0));
        }

        return $available;
    }

    public function calculateReturnTotalOrFail(Sale $origin, array $returnItems): float
    {
        $availableQtyByProduct = $this->availableQtyByProduct($origin);

        $subtotal = 0.0;
        $tax = 0.0;

        foreach ($returnItems as $item) {
            $pid = (int) $item['product_product_id'];
            $qty = (float) $item['quantity'];

            $available = (float) ($availableQtyByProduct[$pid] ?? 0);
            if ($qty <= 0 || $qty > $available + 0.00001) {
                throw ValidationException::withMessages(['return_items' => 'Cantidad a devolver excede lo disponible.']);
            }

            $line = $origin->products->firstWhere('product_product_id', $pid);
            if (! $line) {
                throw ValidationException::withMessages(['return_items' => 'Producto inválido para devolución.']);
            }

            $lineSubtotal = $qty * (float) $line->price;
            $taxRate = (float) ($line->tax_rate ?? 0);
            $taxAmount = $lineSubtotal * ($taxRate / 100);

            $subtotal += $lineSubtotal;
            $tax += $taxAmount;
        }

        return $subtotal + $tax;
    }

    private function deriveCreditNoteCode(string $originDocType, ?string $originJournalCode): ?string
    {
        $originJournalCode = strtoupper(trim((string) $originJournalCode));
        if ($originJournalCode === '') {
            return null;
        }

        if ($originDocType === '03' && preg_match('/^B(\\d+)$/', $originJournalCode, $m)) {
            $digits = (string) $m[1];
            $suffix = strlen($digits) >= 2 ? substr($digits, -2) : str_pad($digits, 2, '0', STR_PAD_LEFT);
            return "BC{$suffix}";
        }

        if ($originDocType === '01' && preg_match('/^F(\\d+)$/', $originJournalCode, $m)) {
            $digits = (string) $m[1];
            $suffix = strlen($digits) >= 2 ? substr($digits, -2) : str_pad($digits, 2, '0', STR_PAD_LEFT);
            return "FC{$suffix}";
        }

        return null;
    }
}

