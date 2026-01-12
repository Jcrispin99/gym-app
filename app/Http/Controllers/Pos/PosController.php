<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\PosConfig;
use App\Models\PosSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PosController extends Controller
{
    /**
     * Show the form for opening a new POS session
     */
    public function open(Request $request)
    {
        $configId = $request->query('config');

        if (! $configId) {
            return redirect()->route('pos-configs.index')
                ->with('error', 'Debe seleccionar una configuración POS');
        }

        $posConfig = PosConfig::with(['warehouse', 'tax'])->findOrFail($configId);

        // Check if POS is active
        if (! $posConfig->is_active) {
            return redirect()->route('pos-configs.index')
                ->with('error', 'Este POS no está activo');
        }

        // Check if user already has an open session
        $existingSession = PosSession::where('user_id', Auth::id())
            ->whereIn('status', [PosSession::STATUS_OPENING_CONTROL, PosSession::STATUS_OPENED])
            ->first();

        if ($existingSession) {
            return redirect()->route('pos.dashboard', $existingSession->id)
                ->with('info', 'Ya tienes una sesión abierta');
        }

        return Inertia::render('Pos/Open', [
            'posConfig' => $posConfig,
        ]);
    }

    /**
     * Store a newly opened POS session
     */
    public function storeOpen(Request $request)
    {
        $validated = $request->validate([
            'pos_config_id' => 'required|exists:pos_configs,id',
            'opening_balance' => 'required|numeric|min:0',
            'opening_note' => 'nullable|string|max:1000',
        ]);

        // Verify POS is active
        $posConfig = PosConfig::findOrFail($validated['pos_config_id']);
        if (! $posConfig->is_active) {
            return back()->withErrors(['pos_config_id' => 'Este POS no está activo']);
        }

        // Check for existing open session
        $existingSession = PosSession::where('user_id', Auth::id())
            ->whereIn('status', [PosSession::STATUS_OPENING_CONTROL, PosSession::STATUS_OPENED])
            ->first();

        if ($existingSession) {
            return redirect()->route('pos.dashboard', $existingSession->id)
                ->with('info', 'Ya tienes una sesión abierta');
        }

        // Create session
        $session = PosSession::create([
            'user_id' => Auth::id(),
            'pos_config_id' => $validated['pos_config_id'],
            'opening_balance' => $validated['opening_balance'],
            'opening_note' => $validated['opening_note'] ?? null,
            'opened_at' => now(),
            'status' => PosSession::STATUS_OPENED,
        ]);

        return redirect()->route('pos.dashboard', $session->id)
            ->with('success', 'Caja abierta exitosamente');
    }

    /**
     * Display the POS dashboard for an active session
     */
    public function dashboard(PosSession $session)
    {
        // Verify session belongs to current user
        if ($session->user_id !== Auth::id()) {
            return redirect()->route('pos-configs.index')
                ->with('error', 'No tienes acceso a esta sesión');
        }

        // Verify session is open
        if (! $session->isOpen()) {
            return redirect()->route('pos-configs.index')
                ->with('error', 'Esta sesión no está abierta');
        }

        // Load relationships
        $session->load(['user', 'posConfig.warehouse', 'posConfig.tax']);

        // Get active payment methods
        $paymentMethods = \App\Models\PaymentMethod::active()->get();

        // Get active customers for client selector (hybrid approach)
        $customers = \App\Models\Partner::customers()
            ->active()
            ->select(['id', 'first_name', 'last_name', 'business_name', 'document_number', 'email', 'phone'])
            ->get()
            ->map(function ($partner) {
                return [
                    'id' => $partner->id,
                    'name' => $partner->display_name,
                    'dni' => $partner->document_number,
                    'email' => $partner->email,
                    'phone' => $partner->phone,
                ];
            });

        return Inertia::render('Pos/Dashboard', [
            'session' => $session,
            'paymentMethods' => $paymentMethods,
            'customers' => $customers,
        ]);
    }

    /**
     * Show the form for closing a POS session
     */
    public function close(PosSession $session)
    {
        // Verify session belongs to current user
        if ($session->user_id !== Auth::id()) {
            return redirect()->route('pos-configs.index')
                ->with('error', 'No tienes acceso a esta sesión');
        }

        // Verify session is not already closed
        if ($session->isClosed()) {
            return redirect()->route('pos-configs.index')
                ->with('info', 'Esta sesión ya está cerrada');
        }

        // Load relationships
        $session->load(['user', 'posConfig.warehouse', 'posConfig.tax']);

        // Get active payment methods
        $paymentMethods = \App\Models\PaymentMethod::active()->get();

        return Inertia::render('Pos/Close', [
            'session' => $session,
            'paymentMethods' => $paymentMethods,
        ]);
    }

    /**
     * Store the closing of a POS session
     */
    public function storeClose(Request $request, PosSession $session)
    {
        // Verify session belongs to current user
        if ($session->user_id !== Auth::id()) {
            return redirect()->route('pos-configs.index')
                ->with('error', 'No tienes acceso a esta sesión');
        }

        // Verify session is not already closed
        if ($session->isClosed()) {
            return redirect()->route('pos-configs.index')
                ->with('info', 'Esta sesión ya está cerrada');
        }

        $validated = $request->validate([
            'closing_balance' => 'required|numeric|min:0',
            'closing_note' => 'nullable|string|max:1000',
            'payments' => 'required|array|min:1',
            'payments.*.payment_method_id' => 'required|exists:payment_methods,id',
            'payments.*.amount' => 'required|numeric|min:0',
        ]);

        // Verify that sum of payments equals closing_balance
        $paymentsTotal = collect($validated['payments'])->sum('amount');
        if (abs($paymentsTotal - $validated['closing_balance']) > 0.01) {
            return back()->withErrors([
                'payments' => 'La suma de los métodos de pago debe ser igual al balance final',
            ]);
        }

        // Update session
        $session->update([
            'closing_balance' => $validated['closing_balance'],
            'closing_note' => $validated['closing_note'] ?? null,
            'closed_at' => now(),
            'status' => PosSession::STATUS_CLOSED,
        ]);

        // Save payment methods
        foreach ($validated['payments'] as $payment) {
            \App\Models\PosSessionPayment::create([
                'pos_session_id' => $session->id,
                'payment_method_id' => $payment['payment_method_id'],
                'amount' => $payment['amount'],
            ]);
        }

        return redirect()->route('pos-configs.index')
            ->with('success', 'Caja cerrada exitosamente');
    }

    /**
     * Show payment view with cart and payment methods
     */
    public function payment(Request $request, PosSession $session)
    {
        // Verify session belongs to current user
        if ($session->user_id !== Auth::id()) {
            return redirect()->route('pos-configs.index')
                ->with('error', 'No tienes acceso a esta sesión');
        }

        // Verify session is open
        if (! $session->isOpen()) {
            return redirect()->route('pos-configs.index')
                ->with('error', 'Esta sesión no está abierta');
        }

        $validated = $request->validate([
            'cart' => 'required|array|min:1',
            'cart.*.product_id' => 'required|integer',
            'cart.*.name' => 'required|string',
            'cart.*.qty' => 'required|integer|min:1',
            'cart.*.price' => 'required|numeric|min:0',
            'cart.*.subtotal' => 'required|numeric|min:0',
            'client_id' => 'nullable|integer', // Removed exists check (using mock clients)
            'total' => 'required|numeric|min:0',
        ]);

        // Load session relationships
        $session->load(['user', 'posConfig.warehouse', 'posConfig.company']);

        // Get journals associated with THIS POS config (not all journals!)
        $journals = $session->posConfig->journals()->get();

        // Get active payment methods
        $paymentMethods = \App\Models\PaymentMethod::active()->get();

        // Get client if selected
        $client = $validated['client_id']
            ? \App\Models\Partner::find($validated['client_id'])
            : null;

        // Get active customers for client selector (hybrid approach)
        $customers = \App\Models\Partner::customers()
            ->active()
            ->select(['id', 'first_name', 'last_name', 'business_name', 'document_number', 'email', 'phone'])
            ->get()
            ->map(function ($partner) {
                return [
                    'id' => $partner->id,
                    'name' => $partner->display_name,
                    'dni' => $partner->document_number,
                    'email' => $partner->email,
                    'phone' => $partner->phone,
                ];
            });

        return Inertia::render('Pos/Payment', [
            'session' => $session,
            'journals' => $journals,
            'paymentMethods' => $paymentMethods,
            'cart' => $validated['cart'],
            'client' => $client,
            'total' => $validated['total'],
            'customers' => $customers,
            'company' => $session->posConfig->company,
        ]);
    }

    /**
     * Process the payment and create sale
     */
    public function processPayment(Request $request, PosSession $session)
    {
        // Verify session
        if ($session->user_id !== Auth::id() || ! $session->isOpen()) {
            return redirect()->route('pos.dashboard', $session->id)
                ->with('error', 'Sesión inválida');
        }

        $validated = $request->validate([
            'journal_id' => 'required|exists:journals,id',
            'cart' => 'required|string', // JSON string
            'client_id' => 'nullable|integer',
            'total' => 'required|numeric|min:0',
            'payments' => 'required|string', // JSON string
        ]);

        // Decode JSON data
        $cart = json_decode($validated['cart'], true);
        $payments = json_decode($validated['payments'], true);

        // Validate cart not empty
        if (empty($cart)) {
            \Log::warning('[POS] Carrito vacío');

            return back()->withErrors(['cart' => 'El carrito está vacío']);
        }

        // Validate payments sum equals total
        $paymentsTotal = collect($payments)->sum('amount');
        if (abs($paymentsTotal - $validated['total']) > 0.01) {
            \Log::warning('[POS] Suma de pagos incorrecta', [
                'expected' => $validated['total'],
                'received' => $paymentsTotal
            ]);

            return back()->withErrors([
                'payments' => 'La suma de los pagos debe ser igual al total',
            ]);
        }

        try {
            DB::transaction(function () use ($validated, $cart, $payments, $session) {
                // 1. Get journal
                $journal = \App\Models\Journal::findOrFail($validated['journal_id']);

                // 2. Generate document number
                $numberParts = \App\Services\SequenceService::getNextParts($journal->id);

                // 3. Get default tax (IGV 18%)
                $defaultTax = \App\Models\Tax::active()->first();

                // 4. Create Sale
                $sale = \App\Models\Sale::create([
                    'serie' => $numberParts['serie'],
                    'correlative' => $numberParts['correlative'],
                    'journal_id' => $journal->id,
                    'partner_id' => $validated['client_id'] ?? null,
                    'warehouse_id' => $session->posConfig->warehouse_id,
                    'company_id' => $session->posConfig->company_id,
                    'pos_session_id' => $session->id,
                    'user_id' => Auth::id(),
                    'status' => 'posted', // Directly posted from POS
                    'payment_status' => 'paid', // Already paid
                    'subtotal' => 0,
                    'tax_amount' => 0,
                    'total' => 0,
                    'notes' => null,
                ]);

                // 5. Create product lines and calculate totals
                $subtotal = 0;
                $totalTax = 0;

                foreach ($cart as $item) {
                    // Get real product
                    $product = \App\Models\ProductProduct::find($item['product_id']);

                    if (! $product) {
                        throw new \Exception("Producto {$item['product_id']} no encontrado");
                    }

                    $quantity = $item['qty'];
                    $price = $item['price'];
                    $lineSubtotal = $quantity * $price;

                    // Calculate tax
                    $taxRate = $defaultTax ? $defaultTax->rate_percent : 0;
                    $taxAmount = $lineSubtotal * ($taxRate / 100);
                    $lineTotal = $lineSubtotal + $taxAmount;

                    // Create line (productable)
                    $sale->products()->create([
                        'product_product_id' => $product->id,
                        'quantity' => $quantity,
                        'price' => $price,
                        'subtotal' => $lineSubtotal,
                        'tax_id' => $defaultTax?->id,
                        'tax_rate' => $taxRate,
                        'tax_amount' => $taxAmount,
                        'total' => $lineTotal,
                    ]);

                    $subtotal += $lineSubtotal;
                    $totalTax += $taxAmount;
                }

                // 6. Update sale totals
                $sale->update([
                    'subtotal' => $subtotal,
                    'tax_amount' => $totalTax,
                    'total' => $subtotal + $totalTax,
                ]);

                // 7. Register payments
                foreach ($payments as $payment) {
                    \App\Models\PosSessionPayment::create([
                        'pos_session_id' => $session->id,
                        'sale_id' => $sale->id,
                        'payment_method_id' => $payment['payment_method_id'],
                        'amount' => $payment['amount'],
                    ]);
                }

                // 8. Reduce inventory (kardex)
                $kardexService = new \App\Services\KardexService;
                foreach ($sale->products as $line) {

                    $kardexService->registerExit(
                        $sale,
                        [
                            'id' => $line->product_product_id,
                            'quantity' => $line->quantity,
                        ],
                        $sale->warehouse_id,
                        "Venta {$sale->document_number}"
                    );
                }
            });

            \Log::info('[POS] Venta creada exitosamente', [
                'document' => DB::table('sales')->latest('id')->first()->serie . '-' . DB::table('sales')->latest('id')->first()->correlative,
                'total' => $validated['total']
            ]);

            return redirect()->route('pos.dashboard', $session->id)
                ->with('success', 'Venta procesada exitosamente');

        } catch (\Exception $e) {
            return back()->withErrors([
                'error' => 'Error al procesar la venta: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * API endpoint to get active customers for POS client selector
     */
    public function apiCustomers(Request $request)
    {
        $customers = \App\Models\Partner::customers()
            ->active()
            ->select(['id', 'first_name', 'last_name', 'business_name', 'document_number', 'email', 'phone'])
            ->get()
            ->map(function ($partner) {
                return [
                    'id' => $partner->id,
                    'name' => $partner->display_name,
                    'dni' => $partner->document_number,
                    'email' => $partner->email,
                    'phone' => $partner->phone,
                ];
            });

        return response()->json($customers);
    }
}
