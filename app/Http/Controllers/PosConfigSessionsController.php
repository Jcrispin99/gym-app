<?php

namespace App\Http\Controllers;

use App\Models\PosConfig;
use App\Models\PosSession;
use App\Models\Sale;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PosConfigSessionsController extends Controller
{
    public function sessions(PosConfig $posConfig)
    {
        $selectedCompanyIds = session('selected_company_ids', []);
        if (! empty($selectedCompanyIds) && ! in_array($posConfig->company_id, $selectedCompanyIds)) {
            abort(404);
        }

        $posConfig->load(['warehouse', 'tax']);

        $sessions = PosSession::where('pos_config_id', $posConfig->id)
            ->with('user')
            ->latest('opened_at')
            ->paginate(15);

        return Inertia::render('PosConfigs/Sessions', [
            'posConfig' => $posConfig,
            'sessions' => $sessions,
        ]);
    }

    public function sessionOrders(Request $request, PosConfig $posConfig, PosSession $session)
    {
        $selectedCompanyIds = session('selected_company_ids', []);
        if (! empty($selectedCompanyIds) && ! in_array($posConfig->company_id, $selectedCompanyIds)) {
            abort(404);
        }

        if ($session->pos_config_id !== $posConfig->id) {
            abort(404);
        }

        $session->load(['user', 'posConfig.warehouse', 'posConfig.company']);

        $orders = Sale::query()
            ->where('pos_session_id', $session->id)
            ->with([
                'partner:id,first_name,last_name,business_name,document_type,document_number',
                'products:id,productable_id,productable_type,product_product_id,quantity,price,subtotal,tax_rate,tax_amount,total',
                'products.productProduct:id,product_template_id,sku,barcode',
                'products.productProduct.productTemplate:id,name',
            ])
            ->latest('id')
            ->get()
            ->map(function (Sale $sale) {
                return [
                    'id' => $sale->id,
                    'serie' => $sale->serie,
                    'correlative' => $sale->correlative,
                    'date' => $sale->date?->toISOString(),
                    'partner' => $sale->partner ? [
                        'id' => $sale->partner->id,
                        'display_name' => $sale->partner->display_name,
                        'document_type' => $sale->partner->document_type,
                        'document_number' => $sale->partner->document_number,
                    ] : null,
                    'subtotal' => (float) $sale->subtotal,
                    'tax_amount' => (float) $sale->tax_amount,
                    'total' => (float) $sale->total,
                    'status' => $sale->status,
                    'payment_status' => $sale->payment_status,
                    'items' => $sale->products->map(function ($line) {
                        return [
                            'id' => $line->id,
                            'product_product_id' => $line->product_product_id,
                            'product_name' => $line->productProduct?->productTemplate?->name,
                            'sku' => $line->productProduct?->sku,
                            'quantity' => (float) $line->quantity,
                            'price' => (float) $line->price,
                            'subtotal' => (float) $line->subtotal,
                            'tax_rate' => (float) $line->tax_rate,
                            'tax_amount' => (float) $line->tax_amount,
                            'total' => (float) $line->total,
                        ];
                    })->values(),
                ];
            })
            ->values();

        return Inertia::render('Pos/Orders', [
            'session' => $session,
            'orders' => $orders,
            'returnTo' => "/pos-configs/{$posConfig->id}/sessions",
        ]);
    }
}

