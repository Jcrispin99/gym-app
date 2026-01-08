<?php

namespace App\Http\Controllers;

use App\Models\PosConfig;
use App\Models\PosSession;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class PosSessionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $sessions = PosSession::with(['user', 'posConfig'])
            ->latest()
            ->paginate(10);

        return Inertia::render('PosSessions/Index', [
            'sessions' => $sessions,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Get available configs for the user or all if admin?
        // For now, all active configs
        $configs = PosConfig::active()->get();

        return Inertia::render('PosSessions/Create', [
            'configs' => $configs,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'pos_config_id' => 'required|exists:pos_configs,id',
            'opening_balance' => 'required|numeric|min:0',
        ]);

        // Check if user already has an open session
        $existingSession = PosSession::where('user_id', Auth::id())
            ->whereIn('status', [PosSession::STATUS_OPENING_CONTROL, PosSession::STATUS_OPENED])
            ->first();

        if ($existingSession) {
            return back()->withErrors(['pos_config_id' => 'You already have an open session.']);
        }

        $session = PosSession::create([
            'user_id' => Auth::id(),
            'pos_config_id' => $validated['pos_config_id'],
            'opening_balance' => $validated['opening_balance'],
            'opened_at' => now(),
            'status' => PosSession::STATUS_OPENED,
        ]);

        return redirect()->route('pos-sessions.show', $session)->with('success', 'Session opened successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(PosSession $posSession)
    {
        $posSession->load(['user', 'posConfig']);
        
        return Inertia::render('PosSessions/Show', [
            'session' => $posSession,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     * We use this for the "Close Session" view usually.
     */
    public function edit(PosSession $posSession)
    {
        // If session is already closed, redirect to show
        if ($posSession->isClosed()) {
            return redirect()->route('pos-sessions.show', $posSession);
        }

        return Inertia::render('PosSessions/Close', [
            'session' => $posSession,
        ]);
    }

    /**
     * Update the specified resource in storage.
     * Used for closing the session.
     */
    public function update(Request $request, PosSession $posSession)
    {
        // If we are just updating status or closing
        if ($request->has('action') && $request->action === 'close') {
            $validated = $request->validate([
                'closing_balance' => 'required|numeric|min:0',
            ]);

            $posSession->update([
                'closing_balance' => $validated['closing_balance'],
                'closed_at' => now(),
                'status' => PosSession::STATUS_CLOSED,
            ]);

            return redirect()->route('pos-sessions.show', $posSession)->with('success', 'Session closed successfully.');
        }

        // Generic update if needed (not implemented yet)
        return back();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PosSession $posSession)
    {
        $posSession->delete();
        return redirect()->route('pos-sessions.index')->with('success', 'Session deleted.');
    }
}
