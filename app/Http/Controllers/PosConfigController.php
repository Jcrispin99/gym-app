<?php

namespace App\Http\Controllers;

use App\Models\Journal;
use App\Models\Partner;
use App\Models\PosConfig;
use App\Models\Tax;
use App\Models\Warehouse;
use Illuminate\Http\Request;
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
            'company_id' => auth()->user()->company_id,
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

        $sessions = \App\Models\PosSession::where('pos_config_id', $posConfig->id)
            ->with('user')
            ->latest('opened_at')
            ->paginate(15);

        return Inertia::render('PosConfigs/Sessions', [
            'posConfig' => $posConfig,
            'sessions' => $sessions,
        ]);
    }
}

