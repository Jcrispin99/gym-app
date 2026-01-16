<?php

namespace App\Http\Controllers;

use App\Models\Journal;
use App\Models\Partner;
use App\Models\PosConfig;
use App\Models\PosSession;
use App\Models\Sale;
use App\Models\Tax;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Spatie\Activitylog\Models\Activity;

class PosConfigController extends Controller
{
    /**
     * Display a listing of POS configs
     */
    public function index()
    {
        $posConfigs = PosConfig::with(['company', 'warehouse', 'tax', 'journals'])
            ->latest()
            ->paginate(20);

        return Inertia::render('PosConfigs/Index', [
            'posConfigs' => $posConfigs,
        ]);
    }

    /**
     * Show the form for creating a new POS config
     */
    public function create()
    {
        $warehouses = Warehouse::all();
        $customers = Partner::customers()->get();
        $taxes = Tax::active()->get();
        $journals = Journal::where('type', 'sale')->get();

        return Inertia::render('PosConfigs/CreateEdit', [
            'warehouses' => $warehouses,
            'customers' => $customers,
            'taxes' => $taxes,
            'journals' => $journals,
        ]);
    }

    /**
     * Store a newly created POS config
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'warehouse_id' => 'required|exists:warehouses,id',
            'default_customer_id' => 'nullable|exists:partners,id',
            'tax_id' => 'nullable|exists:taxes,id',
            'apply_tax' => 'boolean',
            'prices_include_tax' => 'boolean',
            'is_active' => 'boolean',
            'journals' => 'nullable|array',
            'journals.*.journal_id' => 'required|exists:journals,id',
            'journals.*.document_type' => 'required|in:invoice,receipt,credit_note,debit_note',
            'journals.*.is_default' => 'boolean',
        ]);

        $posConfig = PosConfig::create([
            'company_id' => Auth::user()->company_id,
            'name' => $data['name'],
            'warehouse_id' => $data['warehouse_id'],
            'default_customer_id' => $data['default_customer_id'] ?? null,
            'tax_id' => $data['tax_id'] ?? null,
            'apply_tax' => $data['apply_tax'] ?? true,
            'prices_include_tax' => $data['prices_include_tax'] ?? false,
            'is_active' => $data['is_active'] ?? true,
        ]);

        // Attach journals
        if (!empty($data['journals'])) {
            foreach ($data['journals'] as $journal) {
                $posConfig->journals()->attach($journal['journal_id'], [
                    'document_type' => $journal['document_type'],
                    'is_default' => $journal['is_default'] ?? false,
                ]);
            }
        }

        return redirect()->route('pos-configs.index')
            ->with('success', 'POS creado exitosamente');
    }

    /**
     * Display the specified POS config
     */
    public function show(PosConfig $posConfig)
    {
        $posConfig->load(['warehouse', 'defaultCustomer', 'tax', 'journals']);

        return Inertia::render('PosConfigs/Show', [
            'posConfig' => $posConfig,
        ]);
    }

    /**
     * Show the form for editing the specified POS config
     */
    public function edit(PosConfig $posConfig)
    {
        $posConfig->load(['journals']);

        // Get activity log
        $activities = Activity::forSubject($posConfig)
            ->with('causer')
            ->latest()
            ->take(20)
            ->get();

        $warehouses = Warehouse::all();
        $customers = Partner::customers()->get();
        $taxes = Tax::active()->get();
        $journals = Journal::where('type', 'sale')->get();

        return Inertia::render('PosConfigs/CreateEdit', [
            'posConfig' => $posConfig,
            'activities' => $activities,
            'warehouses' => $warehouses,
            'customers' => $customers,
            'taxes' => $taxes,
            'journals' => $journals,
        ]);
    }

    /**
     * Update the specified POS config
     */
    public function update(Request $request, PosConfig $posConfig)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'warehouse_id' => 'required|exists:warehouses,id',
            'default_customer_id' => 'nullable|exists:partners,id',
            'tax_id' => 'nullable|exists:taxes,id',
            'apply_tax' => 'boolean',
            'prices_include_tax' => 'boolean',
            'is_active' => 'boolean',
            'journals' => 'nullable|array',
            'journals.*.journal_id' => 'required|exists:journals,id',
            'journals.*.document_type' => 'required|in:invoice,receipt,credit_note,debit_note',
            'journals.*.is_default' => 'boolean',
        ]);

        $posConfig->update([
            'name' => $data['name'],
            'warehouse_id' => $data['warehouse_id'],
            'default_customer_id' => $data['default_customer_id'] ?? null,
            'tax_id' => $data['tax_id'] ?? null,
            'apply_tax' => $data['apply_tax'] ?? true,
            'prices_include_tax' => $data['prices_include_tax'] ?? false,
            'is_active' => $data['is_active'] ?? true,
        ]);

        // Sync journals
        $posConfig->journals()->detach();
        if (!empty($data['journals'])) {
            foreach ($data['journals'] as $journal) {
                $posConfig->journals()->attach($journal['journal_id'], [
                    'document_type' => $journal['document_type'],
                    'is_default' => $journal['is_default'] ?? false,
                ]);
            }
        }

        return redirect()->route('pos-configs.index')
            ->with('success', 'POS actualizado exitosamente');
    }

    /**
     * Remove the specified POS config
     */
    public function destroy(PosConfig $posConfig)
    {
        $posConfig->journals()->detach();
        $posConfig->delete();

        return redirect()->route('pos-configs.index')
            ->with('success', 'POS eliminado exitosamente');
    }

    /**
     * Toggle POS active status
     */
    public function toggleStatus(PosConfig $posConfig)
    {
        $posConfig->update([
            'is_active' => !$posConfig->is_active,
        ]);

        return back()->with('success', 'Estado actualizado exitosamente');
    }

    /**
     * Display sessions history for a POS config
     */
    public function sessions(PosConfig $posConfig)
    {
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
