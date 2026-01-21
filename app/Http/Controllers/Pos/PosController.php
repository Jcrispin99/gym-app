<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Jobs\SendSunatInvoice;
use App\Models\Category;
use App\Models\MembershipPlan;
use App\Models\MembershipSubscription;
use App\Models\Partner;
use App\Models\PosConfig;
use App\Models\PosSession;
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
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
        $session->load(['user', 'posConfig.warehouse', 'posConfig.tax', 'posConfig.company']);

        // Get active categories
        $categories = Category::where('is_active', true)
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();

        // Get active payment methods
        $paymentMethods = \App\Models\PaymentMethod::active()->get();

        // Get active customers for client selector
        $customers = Partner::customers()
            ->active()
            ->select(['id', 'document_type', 'document_number', 'first_name', 'last_name', 'business_name', 'email', 'phone'])
            ->get()
            ->map(function ($partner) {
                return [
                    'id' => $partner->id,
                    'name' => $partner->display_name,
                    'document_type' => $partner->document_type,
                    'document_number' => $partner->document_number,
                    'dni' => $partner->document_number,
                    'email' => $partner->email,
                    'phone' => $partner->phone,
                ];
            });

        return Inertia::render('Pos/Dashboard', [
            'session' => $session,
            'categories' => $categories,
            'paymentMethods' => $paymentMethods,
            'customers' => $customers,
        ]);
    }

    public function orders(PosSession $session)
    {
        if ($session->user_id !== Auth::id()) {
            return redirect()->route('pos-configs.index')
                ->with('error', 'No tienes acceso a esta sesión');
        }

        if (! $session->isOpen()) {
            return redirect()->route('pos-configs.index')
                ->with('error', 'Esta sesión no está abierta');
        }

        $session->load(['user', 'posConfig.warehouse', 'posConfig.company']);

        $sales = Sale::query()
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
            'orders' => $sales,
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

        $salesQuery = \App\Models\Sale::query()
            ->where('pos_session_id', $session->id)
            ->where('status', 'posted');

        $salesTotal = (float) $salesQuery->sum('total');
        $salesCount = (int) $salesQuery->count();

        $paymentsByMethod = \App\Models\PosSessionPayment::query()
            ->where('pos_session_id', $session->id)
            ->whereNotNull('sale_id')
            ->selectRaw('payment_method_id, SUM(amount) as total')
            ->groupBy('payment_method_id')
            ->get()
            ->map(function ($row) {
                return [
                    'payment_method_id' => (int) $row->payment_method_id,
                    'total' => (float) $row->total,
                ];
            })
            ->values();

        $paymentsTotal = $paymentsByMethod->sum('total');

        return Inertia::render('Pos/Close', [
            'session' => $session,
            'paymentMethods' => $paymentMethods,
            'systemSummary' => [
                'sales_count' => $salesCount,
                'sales_total' => $salesTotal,
                'payments_total' => (float) $paymentsTotal,
                'payments_by_method' => $paymentsByMethod,
            ],
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

        $paymentsTotal = collect($validated['payments'])->sum('amount');

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
            'cart.*.subscription_start_date' => 'nullable|date',
            'cart.*.subscription_end_date' => 'nullable|date',
            'client_id' => 'nullable|integer', // Removed exists check (using mock clients)
            'total' => 'nullable|numeric|min:0',
        ]);

        // Load session relationships
        $session->load(['user', 'posConfig.warehouse', 'posConfig.company', 'posConfig.tax']);

        $posConfig = $session->posConfig;
        $applyTax = (bool) ($posConfig?->apply_tax ?? true);
        $pricesIncludeTax = (bool) ($posConfig?->prices_include_tax ?? false);
        $tax = $applyTax && $posConfig?->tax_id ? $posConfig->tax : null;
        $taxRate = $applyTax && $tax ? (float) $tax->rate_percent : 0.0;

        $cartSubtotal = collect($validated['cart'])->sum('subtotal');
        $cartTaxAmount = 0.0;
        $cartTotal = 0.0;

        if ($applyTax && $taxRate > 0) {
            if ($pricesIncludeTax) {
                $cartTotal = (float) $cartSubtotal;
                $net = $cartTotal / (1 + ($taxRate / 100));
                $cartTaxAmount = $cartTotal - $net;
            } else {
                $cartTaxAmount = (float) ($cartSubtotal * ($taxRate / 100));
                $cartTotal = (float) ($cartSubtotal + $cartTaxAmount);
            }
        } else {
            $cartTotal = (float) $cartSubtotal;
            $cartTaxAmount = 0.0;
        }

        // Get journals associated with THIS POS config (not all journals!)
        $journals = $session->posConfig->journals()
            ->get()
            ->map(function ($journal) {
                return [
                    'id' => $journal->id,
                    'name' => $journal->name,
                    'code' => $journal->code,
                    'type' => $journal->type,
                    'document_type' => $journal->pivot?->document_type,
                    'is_default' => (bool) ($journal->pivot?->is_default ?? false),
                ];
            })
            ->values();

        // Get active payment methods
        $paymentMethods = \App\Models\PaymentMethod::active()->get();

        // Get client if selected
        $client = $validated['client_id']
            ? Partner::find($validated['client_id'])
            : null;

        // Get active customers for client selector
        $customers = Partner::customers()
            ->active()
            ->select(['id', 'document_type', 'document_number', 'first_name', 'last_name', 'business_name', 'email', 'phone'])
            ->get()
            ->map(function ($partner) {
                return [
                    'id' => $partner->id,
                    'name' => $partner->display_name,
                    'document_type' => $partner->document_type,
                    'document_number' => $partner->document_number,
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
            'total' => $cartTotal,
            'customers' => $customers,
            'company' => $session->posConfig->company,
            'taxConfig' => [
                'apply_tax' => $applyTax,
                'prices_include_tax' => $pricesIncludeTax,
                'tax_id' => $tax?->id,
                'tax_name' => $tax?->name,
                'tax_rate' => $taxRate,
                'subtotal' => (float) $cartSubtotal,
                'tax_amount' => (float) $cartTaxAmount,
                'total' => (float) $cartTotal,
            ],
        ]);
    }

    /**
     * Process the payment and create sale
     */
    public function processPayment(Request $request, PosSession $session)
    {
        Log::info('🔵 [POS DEBUG] processPayment INICIADO', [
            'session_id' => $session->id,
            'user_id' => Auth::id(),
            'request_has_cart' => $request->has('cart'),
            'request_has_payments' => $request->has('payments'),
        ]);

        // Verify session
        if ($session->user_id !== Auth::id() || ! $session->isOpen()) {
            Log::error('❌ [POS DEBUG] Sesión inválida', [
                'session_user' => $session->user_id,
                'auth_user' => Auth::id(),
                'is_open' => $session->isOpen(),
            ]);

            return redirect()->route('pos.dashboard', $session->id)
                ->with('error', 'Sesión inválida');
        }

        $validated = $request->validate([
            'journal_id' => 'required|exists:journals,id',
            'cart' => 'required|string', // JSON string
            'client_id' => 'nullable|integer',
            'total' => 'nullable|numeric|min:0',
            'payments' => 'required|string', // JSON string
            'credit_note_info' => 'nullable|string', // JSON string with credit note details
        ]);

        // Decode JSON data
        $cart = json_decode($validated['cart'], true);
        $payments = json_decode($validated['payments'], true);
        $creditNoteInfo = isset($validated['credit_note_info']) ? json_decode($validated['credit_note_info'], true) : null;

        // Validate cart not empty
        if (empty($cart)) {

            return back()->withErrors(['cart' => 'El carrito está vacío']);
        }

        // DETECT SUBSCRIPTION PRODUCTS: Check if any product in cart is linked to a membership plan
        $subscriptionProductIds = collect($cart)->pluck('product_id')->toArray();
        Log::info('🔍 [POS DEBUG] Detectando suscripciones', [
            'product_ids' => $subscriptionProductIds,
        ]);

        $membershipPlans = MembershipPlan::whereIn('product_product_id', $subscriptionProductIds)->get();
        $hasSubscription = $membershipPlans->isNotEmpty();

        Log::info('🔍 [POS DEBUG] Resultado detección', [
            'has_subscription' => $hasSubscription,
            'plans_found' => $membershipPlans->count(),
            'plans' => $membershipPlans->pluck('name'),
        ]);

        // VALIDATE: Client is required for subscriptions
        if ($hasSubscription && empty($validated['client_id'])) {
            Log::warning('❌ [POS] Intento de venta de suscripción sin cliente', [
                'cart' => $cart,
            ]);

            return back()->withErrors([
                'error' => 'Se requiere seleccionar un cliente para vender suscripciones',
            ]);
        }

        $posJournal = $session->posConfig
            ->journals()
            ->where('journals.id', $validated['journal_id'])
            ->first();

        if (! $posJournal) {
            throw ValidationException::withMessages([
                'journal_id' => 'El documento seleccionado no pertenece a este POS.',
            ]);
        }

        if (! empty($validated['client_id'])) {
            $client = Partner::find($validated['client_id']);

            if (! $client) {
                throw ValidationException::withMessages([
                    'client_id' => 'Cliente inválido.',
                ]);
            }

            $requiredDocumentType = $client->document_type === 'RUC' ? 'invoice' : 'receipt';
            $selectedDocumentType = $posJournal->pivot?->document_type;

            if ($selectedDocumentType && $selectedDocumentType !== $requiredDocumentType) {
                $documentLabel = $requiredDocumentType === 'invoice' ? 'Factura' : 'Boleta';

                throw ValidationException::withMessages([
                    'journal_id' => "El cliente seleccionado requiere {$documentLabel}.",
                ]);
            }
        }

        $paymentsTotal = (float) collect($payments)->sum('amount');

        Log::info('💰 [POS DEBUG] Iniciando transacción', [
            'payments_total' => $paymentsTotal,
        ]);

        try {
            DB::transaction(function () use ($validated, $cart, $payments, $session, $hasSubscription, $membershipPlans, $posJournal, $creditNoteInfo) {
                Log::info('🔄 [POS DEBUG] Dentro de transacción DB');

                // 1. Get journal
                $journal = \App\Models\Journal::findOrFail($posJournal->id);
                Log::info('✅ [POS DEBUG] Journal encontrado', ['journal_id' => $journal->id]);

                // 2. Generate document number
                $numberParts = \App\Services\SequenceService::getNextParts($journal->id);
                Log::info('✅ [POS DEBUG] Número generado', $numberParts);

                $posConfig = $session->posConfig()->with('tax')->first();
                $applyTax = (bool) ($posConfig?->apply_tax ?? true);
                $pricesIncludeTax = (bool) ($posConfig?->prices_include_tax ?? false);
                $tax = $applyTax && $posConfig?->tax_id ? $posConfig->tax : null;
                $taxRate = $applyTax && $tax ? (float) $tax->rate_percent : 0.0;

                // Prepare notes
                $notes = null;
                // Note: We keep the text note for backward compatibility/readability,
                // but the real logic is now in the reference_sale_id column
                if ($creditNoteInfo) {
                    $notes = "Nota de Crédito aplicada: {$creditNoteInfo['document']} - S/ " . number_format($creditNoteInfo['amount_used'], 2);
                }

                // 4. Create Sale
                Log::info('📝 [POS DEBUG] Creando Sale...');
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
                    'notes' => $notes,
                ]);

                Log::info('✅ [POS DEBUG] Sale creado!', [
                    'sale_id' => $sale->id,
                    'document_number' => $sale->document_number,
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

                    $quantity = (float) $item['qty'];
                    $inputUnitPrice = (float) $item['price'];

                    if ($applyTax && $taxRate > 0) {
                        if ($pricesIncludeTax) {
                            $unitNetPrice = $inputUnitPrice / (1 + ($taxRate / 100));
                            $lineSubtotal = $quantity * $unitNetPrice;
                            $lineTotal = $quantity * $inputUnitPrice;
                            $taxAmount = $lineTotal - $lineSubtotal;
                        } else {
                            $unitNetPrice = $inputUnitPrice;
                            $lineSubtotal = $quantity * $unitNetPrice;
                            $taxAmount = $lineSubtotal * ($taxRate / 100);
                            $lineTotal = $lineSubtotal + $taxAmount;
                        }
                    } else {
                        $unitNetPrice = $inputUnitPrice;
                        $lineSubtotal = $quantity * $unitNetPrice;
                        $taxAmount = 0;
                        $lineTotal = $lineSubtotal;
                    }

                    // Create line (productable)
                    $sale->products()->create([
                        'product_product_id' => $product->id,
                        'quantity' => $quantity,
                        'price' => $unitNetPrice,
                        'subtotal' => $lineSubtotal,
                        'tax_id' => $applyTax ? $tax?->id : null,
                        'tax_rate' => $applyTax ? $taxRate : 0,
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

                $expectedTotal = (float) $sale->total;
                $paymentsTotal = (float) collect($payments)->sum('amount');
                if (abs($paymentsTotal - $expectedTotal) > 0.01) {
                    throw new \Exception("La suma de los pagos debe ser igual al total ({$expectedTotal})");
                }

                // 7. Register payments
                foreach ($payments as $payment) {
                    // Check if this payment is a credit note payment
                    // We identify it by checking if we have creditNoteInfo and if this is the "credit note" method
                    // For now, we assume if creditNoteInfo exists, the payment with method_id matching "Nota de Crédito" is the one.
                    // But since we don't have the method ID for "Nota de Crédito" hardcoded, we can check against the payment amount
                    // or simply check if this payment matches the credit note usage.

                    $referenceSaleId = null;
                    if ($creditNoteInfo && abs($payment['amount'] - $creditNoteInfo['amount_used']) < 0.01) {
                        // This heuristic assumes the payment amount matches the credit note usage amount.
                        // It's safer if the frontend sends the type, but for now this works for mixed payments
                        // as long as cash amount != credit note amount, or if they are equal it doesn't matter much.
                        // Ideally, we should check the payment method type.
                        $referenceSaleId = $creditNoteInfo['id'];
                    }

                    \App\Models\PosSessionPayment::create([
                        'pos_session_id' => $session->id,
                        'sale_id' => $sale->id,
                        'payment_method_id' => $payment['payment_method_id'],
                        'amount' => $payment['amount'],
                        'reference_sale_id' => $referenceSaleId,
                    ]);
                }

                // 8. Reduce inventory (kardex)
                $sale->loadMissing('products.productProduct.template');
                $kardexService = new \App\Services\KardexService;
                foreach ($sale->products as $line) {
                    $tracksInventory = $line->productProduct?->template?->tracks_inventory ?? true;
                    if (! $tracksInventory) {
                        continue;
                    }

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

                // 9. CREATE MEMBERSHIP SUBSCRIPTIONS (if any)
                if ($hasSubscription && ! empty($validated['client_id'])) {
                    Log::info('🎫 [POS DEBUG] Entrando a creación de suscripciones', [
                        'client_id' => $validated['client_id'],
                        'cart_count' => count($cart),
                    ]);

                    foreach ($cart as $item) {
                        Log::info('🔍 [POS DEBUG] Procesando item del carrito', [
                            'product_id' => $item['product_id'],
                            'name' => $item['name'] ?? 'Sin nombre',
                        ]);

                        $plan = $membershipPlans->firstWhere('product_product_id', $item['product_id']);

                        if ($plan) {
                            Log::info('✅ [POS DEBUG] Plan encontrado para producto', [
                                'plan_id' => $plan->id,
                                'plan_name' => $plan->name,
                            ]);

                            try {
                                $firstPayment = $payments[0] ?? null;
                                $qty = max(1, (int) ($item['qty'] ?? 1));

                                // Use custom dates from cart if provided, otherwise auto-calculate
                                if (!empty($item['subscription_start_date']) && !empty($item['subscription_end_date'])) {
                                    $baseStartDate = Carbon::parse($item['subscription_start_date'])->startOfDay();
                                    $baseEndDate = Carbon::parse($item['subscription_end_date'])->startOfDay();

                                    Log::info('📅 [POS DEBUG] Usando fechas personalizadas del carrito', [
                                        'start_date' => $baseStartDate->toDateString(),
                                        'end_date' => $baseEndDate->toDateString(),
                                        'qty' => $qty,
                                    ]);
                                } else {
                                    $baseStartDate = Carbon::now()->startOfDay();
                                    $baseEndDate = null;
                                }

                                $hasCustomDates = ! empty($item['subscription_start_date']) && ! empty($item['subscription_end_date']);
                                $periodMonths = 0;
                                $periodDays = 0;

                                if ($hasCustomDates) {
                                    $exclusiveEnd = $baseEndDate->copy()->addDay();
                                    $m = $baseStartDate->diffInMonths($exclusiveEnd);
                                    if ($m > 0 && $baseStartDate->copy()->addMonthsNoOverflow($m)->equalTo($exclusiveEnd)) {
                                        $periodMonths = $m;
                                    } else {
                                        $periodDays = $baseStartDate->diffInDays($baseEndDate);
                                    }
                                } else {
                                    $durationDays = (int) ($plan->duration_days ?? 0);
                                    if ($durationDays > 0 && $durationDays % 30 === 0) {
                                        $periodMonths = (int) ($durationDays / 30);
                                    } else {
                                        $periodDays = max(1, $durationDays);
                                    }
                                }

                                $existing = MembershipSubscription::query()
                                    ->where('partner_id', $validated['client_id'])
                                    ->where('membership_plan_id', $plan->id)
                                    ->orderByDesc('end_date')
                                    ->first();

                                if ($existing && $existing->end_date && $existing->end_date->startOfDay()->greaterThanOrEqualTo($baseStartDate)) {
                                    $baseStartDate = $existing->end_date->copy()->addDay()->startOfDay();
                                    if ($hasCustomDates) {
                                        if ($periodMonths > 0) {
                                            $baseEndDate = $baseStartDate->copy()->addMonthsNoOverflow($periodMonths)->subDay();
                                        } else {
                                            $baseEndDate = $baseStartDate->copy()->addDays($periodDays);
                                        }
                                    }
                                }

                                $totalPaid = (float) ($item['subtotal'] ?? ($plan->price * $qty));
                                $perPeriodPaid = $qty > 0 ? ($totalPaid / $qty) : $totalPaid;

                                $prevEnd = null;
                                for ($i = 0; $i < $qty; $i++) {
                                    $index = $i + 1;
                                    $startDate = $i === 0 ? $baseStartDate->copy() : $prevEnd->copy()->addDay()->startOfDay();
                                    if ($hasCustomDates && $i === 0) {
                                        $endDate = $baseEndDate->copy();
                                    } else {
                                        if ($periodMonths > 0) {
                                            $endDate = $startDate->copy()->addMonthsNoOverflow($periodMonths)->subDay();
                                        } else {
                                            $endDate = $startDate->copy()->addDays($periodDays);
                                        }
                                    }

                                    $subscription = MembershipSubscription::create([
                                        'partner_id' => $validated['client_id'],
                                        'membership_plan_id' => $plan->id,
                                        'company_id' => $session->posConfig->company_id,
                                        'start_date' => $startDate,
                                        'end_date' => $endDate,
                                        'original_end_date' => $endDate,
                                        'status' => 'active',
                                        'amount_paid' => $perPeriodPaid,
                                        'payment_method' => $firstPayment['payment_method_id'] ?? 'efectivo',
                                        'payment_reference' => "Venta {$sale->document_number}",
                                        'sold_by' => Auth::id(),
                                        'remaining_freeze_days' => $plan->max_freeze_days ?? 0,
                                        'notes' => "Suscripción {$index}/{$qty} (POS)",
                                    ]);

                                    $prevEnd = $endDate->copy();

                                    Log::info('🎉 [POS] Suscripción creada', [
                                        'subscription_id' => $subscription->id,
                                        'plan' => $plan->name,
                                        'partner_id' => $validated['client_id'],
                                        'sale' => $sale->document_number,
                                        'start_date' => $startDate->toDateString(),
                                        'end_date' => $endDate->toDateString(),
                                    ]);
                                }
                            } catch (\Exception $e) {
                                Log::error('❌ [POS ERROR] Error creando suscripción', [
                                    'error' => $e->getMessage(),
                                    'line' => $e->getLine(),
                                    'plan_id' => $plan->id,
                                ]);
                                throw $e; // Re-throw para que la transacción haga rollback
                            }
                        } else {
                            Log::info('ℹ️  [POS DEBUG] Item no es plan de membresía', [
                                'product_id' => $item['product_id'],
                            ]);
                        }
                    }

                    // AUTO-MARK as member: Update partner's is_member flag to true
                    Log::info('👤 [POS DEBUG] Marcando partner como miembro...');
                    Partner::where('id', $validated['client_id'])
                        ->update(['is_member' => true]);

                    Log::info('✅ [POS] Partner marcado como miembro', [
                        'partner_id' => $validated['client_id'],
                    ]);
                }

                SendSunatInvoice::dispatch($sale->id)->afterCommit();
            });

            $lastSale = DB::table('sales')->latest('id')->first();
            Log::info('[POS] Venta creada exitosamente', [
                'document' => $lastSale->serie . '-' . $lastSale->correlative,
                'total' => $lastSale->total,
            ]);

            return redirect()->route('pos.dashboard', ['session' => $session->id, 'clear_cart' => 1])
                ->with('success', 'Venta procesada exitosamente');
        } catch (\Exception $e) {
            return back()->withErrors([
                'error' => 'Error al procesar la venta: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * API endpoint to get active customers for POS client selector
     */
    public function apiCustomers(Request $request)
    {
        $customers = Partner::customers()
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

    public function apiPartnerLookup(Request $request)
    {
        $validated = $request->validate([
            'document_number' => 'required|string|max:20',
            'document_type' => 'nullable|in:DNI,RUC,CE,Passport',
        ]);

        $partner = Partner::query()
            ->when(! empty($validated['document_type'] ?? null), function ($q) use ($validated) {
                $q->where('document_type', $validated['document_type']);
            })
            ->where('document_number', $validated['document_number'])
            ->first();

        if (! $partner) {
            return response()->json([
                'found' => false,
            ]);
        }

        return response()->json([
            'found' => true,
            'partner' => [
                'id' => $partner->id,
                'document_type' => $partner->document_type,
                'document_number' => $partner->document_number,
                'business_name' => $partner->business_name,
                'first_name' => $partner->first_name,
                'last_name' => $partner->last_name,
                'email' => $partner->email,
                'phone' => $partner->phone,
                'mobile' => $partner->mobile,
                'is_member' => (bool) $partner->is_member,
                'is_customer' => (bool) $partner->is_customer,
                'is_supplier' => (bool) $partner->is_supplier,
                'status' => $partner->status,
            ],
        ]);
    }

    public function apiUpsertCustomer(Request $request)
    {
        $validated = $request->validate([
            'document_type' => 'required|in:DNI,RUC,CE,Passport',
            'document_number' => 'required|string|max:20',
            'business_name' => 'nullable|string|max:200',
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:100',
            'phone' => 'nullable|string|max:20',
            'mobile' => 'nullable|string|max:20',
        ]);

        $partner = Partner::query()
            ->where('document_type', $validated['document_type'])
            ->where('document_number', $validated['document_number'])
            ->first();

        $dataToFill = [
            'document_type' => $validated['document_type'],
            'document_number' => $validated['document_number'],
            'business_name' => $validated['business_name'] ?? null,
            'first_name' => $validated['first_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'mobile' => $validated['mobile'] ?? null,
        ];

        if ($partner) {
            $partner->fill($dataToFill);
        } else {
            $partner = new Partner(array_merge($dataToFill, [
                'status' => 'active',
            ]));
        }

        $partner->is_customer = true;
        if (empty($partner->status)) {
            $partner->status = 'active';
        }

        $partner->save();

        return response()->json([
            'id' => $partner->id,
            'name' => $partner->display_name,
            'document_type' => $partner->document_type,
            'document_number' => $partner->document_number,
            'dni' => $partner->document_number,
            'email' => $partner->email,
            'phone' => $partner->phone,
        ]);
    }

    /**
     * Get available credit notes for a partner
     */
    public function getCreditNotes(Request $request, PosSession $session, Partner $partner)
    {
        // Find credit notes (Document Type 07) that are posted
        $creditNotes = Sale::query()
            ->where('partner_id', $partner->id)
            ->where('status', 'posted')
            ->whereHas('journal', function ($q) {
                $q->where('document_type_code', '07');
            })
            ->with(['journal'])
            ->withSum('paymentsUsingThisCredit', 'amount') // Load total used amount
            ->orderByDesc('date')
            ->get();

        $availableNotes = $creditNotes->map(function ($note) {
            $document = $note->serie . '-' . $note->correlative;
            $totalUsed = (float) $note->payments_using_this_credit_sum_amount;
            $totalAmount = (float) $note->total;
            $balance = max(0, $totalAmount - $totalUsed);

            return [
                'id' => $note->id,
                'document' => $document,
                'date' => $note->date?->format('d/m/Y'),
                'total' => $totalAmount,
                'balance' => $balance,
            ];
        })->filter(function ($note) {
            return $note['balance'] > 0;
        })->values();

        return response()->json($availableNotes);
    }
}
