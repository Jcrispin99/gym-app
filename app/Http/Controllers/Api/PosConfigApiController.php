<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Journal;
use App\Models\Partner;
use App\Models\PosConfig;
use App\Models\Tax;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;

class PosConfigApiController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'company_id' => 'nullable|integer|exists:companies,id',
            'is_active' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = PosConfig::query()
            ->with(['company', 'warehouse', 'defaultCustomer', 'tax', 'journals'])
            ->latest();

        if (! empty($validated['company_id'])) {
            $query->where('company_id', (int) $validated['company_id']);
        }

        if (array_key_exists('is_active', $validated) && $validated['is_active'] !== null) {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where('name', 'like', "%{$search}%");
        }

        $perPage = (int) ($validated['per_page'] ?? 20);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function formOptions(Request $request)
    {
        $warehouses = Warehouse::query()->latest()->get();
        $customers = Partner::query()->customers()->get();
        $taxes = Tax::query()->active()->orderByDesc('is_default')->orderBy('name')->get();
        $journals = Journal::query()->where('type', 'sale')->orderBy('name')->get();

        return response()->json([
            'data' => [
                'warehouses' => $warehouses,
                'customers' => $customers,
                'taxes' => $taxes,
                'journals' => $journals,
            ],
        ]);
    }

    public function show(PosConfig $posConfig)
    {
        $posConfig->load(['company', 'warehouse', 'defaultCustomer', 'tax', 'journals']);

        $activities = Activity::forSubject($posConfig)
            ->with('causer')
            ->latest()
            ->take(20)
            ->get();

        return response()->json([
            'data' => $posConfig,
            'meta' => [
                'activities' => $activities,
            ],
        ]);
    }

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

        if (! empty($data['journals'])) {
            foreach ($data['journals'] as $journal) {
                $posConfig->journals()->attach($journal['journal_id'], [
                    'document_type' => $journal['document_type'],
                    'is_default' => $journal['is_default'] ?? false,
                ]);
            }
        }

        return response()->json([
            'data' => $posConfig->fresh()->load(['company', 'warehouse', 'defaultCustomer', 'tax', 'journals']),
        ], 201);
    }

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

        $posConfig->journals()->detach();
        if (! empty($data['journals'])) {
            foreach ($data['journals'] as $journal) {
                $posConfig->journals()->attach($journal['journal_id'], [
                    'document_type' => $journal['document_type'],
                    'is_default' => $journal['is_default'] ?? false,
                ]);
            }
        }

        return response()->json([
            'data' => $posConfig->fresh()->load(['company', 'warehouse', 'defaultCustomer', 'tax', 'journals']),
        ]);
    }

    public function destroy(PosConfig $posConfig)
    {
        $posConfig->journals()->detach();
        $posConfig->delete();

        return response()->json([
            'ok' => true,
        ]);
    }

    public function toggleStatus(PosConfig $posConfig)
    {
        $posConfig->update([
            'is_active' => ! $posConfig->is_active,
        ]);

        return response()->json([
            'data' => $posConfig->fresh()->load(['company', 'warehouse', 'defaultCustomer', 'tax', 'journals']),
        ]);
    }
}
