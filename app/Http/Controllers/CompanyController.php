<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CompanyController extends Controller
{
    /**
     * Display a listing of companies
     */
    public function indexPage()
    {
        $mainOffice = Company::mainOffices()->first();
        $branches = [];
        
        if ($mainOffice) {
            $branches = $mainOffice->branches()
                ->orderBy('branch_code')
                ->get();
        }
        
        return Inertia::render('Companies/Index', [
            'main_office' => $mainOffice,
            'branches' => $branches,
        ]);
    }

    /**
     * Show create company form
     */
    public function create()
    {
        $mainOffice = Company::mainOffices()->first();
        
        return Inertia::render('Companies/Create', [
            'main_office' => $mainOffice,
        ]);
    }

    /**
     * Show edit company form
     */
    public function edit(Company $company)
    {
        $company->load('parent');
        
        // Get activity log
        $activities = $company->activities()
            ->with('causer')
            ->latest()
            ->take(20)
            ->get()
            ->map(function ($activity) {
                return [
                    'description' => $activity->description,
                    'event' => $activity->event,
                    'properties' => $activity->properties,
                    'created_at' => $activity->created_at,
                    'causer' => $activity->causer ? [
                        'name' => $activity->causer->name,
                        'email' => $activity->causer->email,
                    ] : null,
                ];
            });

        $mainOffice = Company::mainOffices()->first();

        return Inertia::render('Companies/Edit', [
            'company' => $company,
            'main_office' => $mainOffice,
            'activities' => $activities,
        ]);
    }

    /**
     * Get all companies (main office + branches) - API endpoint
     */
    public function index()
    {
        $mainOffice = Company::mainOffices()->first();
        
        if (!$mainOffice) {
            return response()->json([
                'main_office' => null,
                'branches' => [],
            ]);
        }

        $branches = $mainOffice->branches()
            ->active()
            ->orderBy('branch_code')
            ->get(['id', 'trade_name', 'branch_code', 'district']);

        return response()->json([
            'main_office' => [
                'id' => $mainOffice->id,
                'trade_name' => $mainOffice->trade_name,
                'branch_code' => null,
                'district' => $mainOffice->district,
            ],
            'branches' => $branches,
        ]);
    }

    /**
     * Switch to different companies/branches (supports multiple selection)
     */
    public function switch(Request $request)
    {
        $request->validate([
            'company_ids' => 'required|array|min:1',
            'company_ids.*' => 'exists:companies,id',
        ]);

        $companyIds = $request->company_ids;

        // Validate companies exist
        $companies = Company::whereIn('id', $companyIds)->get();
        
        if ($companies->isEmpty()) {
            return back()->withErrors(['error' => 'No se encontraron compañías válidas']);
        }

        // Store selected companies in session
        session(['selected_company_ids' => $companyIds]);

        $names = $companies->pluck('trade_name')->join(', ');
        return back()->with('success', 'Filtrando por: ' . $names);
    }

    /**
     * Store a new company/branch
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'business_name' => 'required|string|max:255',
            'trade_name' => 'required|string|max:255',
            'ruc' => 'required|string|size:11',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'ubigeo' => 'nullable|string|size:6',
            'urbanization' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:50',
            'province' => 'nullable|string|max:50',
            'district' => 'nullable|string|max:50',
            'parent_id' => 'nullable|exists:companies,id',
            'branch_code' => 'nullable|string|max:10|unique:companies,branch_code',
            'is_main_office' => 'boolean',
        ]);

        $company = Company::create($validated);

        return back()->with('success', 'Compañía creada exitosamente');
    }

    /**
     * Update an existing company/branch
     */
    public function update(Request $request, Company $company)
    {
        $validated = $request->validate([
            'business_name' => 'required|string|max:255',
            'trade_name' => 'required|string|max:255',
            'ruc' => 'required|string|size:11',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'ubigeo' => 'nullable|string|size:6',
            'urbanization' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:50',
            'province' => 'nullable|string|max:50',
            'district' => 'nullable|string|max:50',
            'parent_id' => 'nullable|exists:companies,id',
            'branch_code' => 'nullable|string|max:10|unique:companies,branch_code,' . $company->id,
            'is_main_office' => 'boolean',
        ]);

        $company->update($validated);

        return back()->with('success', 'Compañía actualizada exitosamente');
    }

    /**
     * Delete a company/branch
     */
    public function destroy(Company $company)
    {
        // Prevent deleting main office if it has branches
        if ($company->isMainOffice() && $company->branches()->count() > 0) {
            return back()->withErrors([
                'error' => 'No se puede eliminar la casa matriz mientras tenga sucursales'
            ]);
        }

        $company->delete();

        return back()->with('success', 'Compañía eliminada exitosamente');
    }
}
