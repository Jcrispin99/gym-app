<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class UserController extends Controller
{
    /**
     * Display a listing of users
     */
    public function index()
    {
        $companyIds = session('selected_company_ids', []);
        
        $query = User::with('companies')->latest();
        
        // Filter by selected companies using pivot table
        if (!empty($companyIds)) {
            $query->whereHas('companies', function($q) use ($companyIds) {
                $q->whereIn('companies.id', $companyIds);
            });
        }
        
        $users = $query->get();

        return Inertia::render('Users/Index', [
            'users' => $users,
        ]);
    }

    /**
     * Show create user form
     */
    public function create()
    {
        $companies = Company::orderBy('trade_name')->get();

        return Inertia::render('Users/Create', [
            'companies' => $companies,
        ]);
    }

    /**
     * Store a new user
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'company_ids' => 'required|array|min:1',
            'company_ids.*' => 'exists:companies,id',
        ]);

        $validated['password'] = Hash::make($validated['password']);
        
        // Keep first company as primary for backward compatibility
        $validated['company_id'] = $validated['company_ids'][0];

        $user = User::create($validated);
        
        // Sync companies (many-to-many)
        $user->companies()->sync($validated['company_ids']);

        return redirect()->route('users.index')
            ->with('success', 'Usuario creado exitosamente');
    }

    /**
     * Show edit user form
     */
    public function edit(User $user)
    {
        $user->load('companies');

        // Get activity log
        $activities = $user->activities()
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

        $companies = Company::orderBy('trade_name')->get();

        return Inertia::render('Users/Edit', [
            'user' => array_merge($user->toArray(), [
                'company_ids' => $user->companies->pluck('id')->toArray(),
            ]),
            'companies' => $companies,
            'activities' => $activities,
        ]);
    }

    /**
     * Update user
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'company_ids' => 'required|array|min:1',
            'company_ids.*' => 'exists:companies,id',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }
        
        // Keep first company as primary for backward compatibility
        $validated['company_id'] = $validated['company_ids'][0];

        $user->update($validated);
        
        // Sync companies (many-to-many)
        $user->companies()->sync($validated['company_ids']);

        return back()->with('success', 'Usuario actualizado exitosamente');
    }

    /**
     * Delete user
     */
    public function destroy(User $user)
    {
        // Prevent deleting own account
        if ($user->id === auth()->id()) {
            return back()->withErrors([
                'error' => 'No puedes eliminar tu propia cuenta',
            ]);
        }

        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'Usuario eliminado exitosamente');
    }
}
