<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestPosFlow extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pos:test-flow';

    protected $description = 'Test complete POS flow including sales, subscriptions, credit notes, and payments';

    public function handle()
    {
        $this->info('🚀 Starting POS Flow Test...');

        // 1. SETUP
        $this->info("\n[1/6] Setting up environment...");

        // Create or get user
        $user = \App\Models\User::firstOrCreate(
            ['email' => 'test@pos.com'],
            ['name' => 'Test User', 'password' => bcrypt('password')]
        );
        auth()->login($user); // Simulate login for Auth::id() calls

        // Create or get partner
        $partner = \App\Models\Partner::firstOrCreate(
            ['document_number' => '99999999'],
            [
                'document_type' => 'DNI',
                'first_name' => 'Test',
                'last_name' => 'Customer',
                'status' => 'active',
                'is_customer' => true
            ]
        );

        // Create or get Warehouse & Company
        $company = \App\Models\Company::first() ?? \App\Models\Company::create([
            'business_name' => 'Test Company',
            'ruc' => '20000000001',
            'status' => 'active'
        ]);

        $warehouse = \App\Models\Warehouse::first() ?? \App\Models\Warehouse::create([
            'name' => 'Test Warehouse',
            'company_id' => $company->id
        ]);

        // Create Tax (IGV)
        $tax = \App\Models\Tax::firstOrCreate(
            ['name' => 'IGV'],
            ['rate_percent' => 18, 'is_active' => true, 'tax_type' => 'VAT']
        );

        // Create Product Category
        $category = \App\Models\Category::firstOrCreate(
            ['name' => 'Test Category'],
            ['parent_id' => null, 'slug' => 'test-category']
        );

        // Create Products
        // A. Standard Product
        $productTemplate = \App\Models\ProductTemplate::firstOrCreate(
            ['name' => 'Test Product'],
            [
                'list_price' => 100,
                'type' => 'product',
                'company_id' => $company->id,
                'category_id' => $category->id
            ]
        );
        $product = \App\Models\ProductProduct::firstOrCreate(
            ['product_template_id' => $productTemplate->id],
            ['sku' => 'TEST-PROD-001']
        );

        // B. Subscription Product
        $subTemplate = \App\Models\ProductTemplate::firstOrCreate(
            ['name' => 'Test Membership 1 Month'],
            [
                'list_price' => 150,
                'type' => 'service',
                'company_id' => $company->id,
                'category_id' => $category->id
            ]
        );
        $subProduct = \App\Models\ProductProduct::firstOrCreate(
            ['product_template_id' => $subTemplate->id],
            ['sku' => 'TEST-SUB-001']
        );
        // Ensure membership plan exists
        \App\Models\MembershipPlan::firstOrCreate(
            ['product_product_id' => $subProduct->id],
            [
                'name' => 'Plan Mensual Test',
                'duration_days' => 30,
                'price' => 150,
                'company_id' => $company->id
            ]
        );

        // Create Payment Methods
        $cashMethod = \App\Models\PaymentMethod::firstOrCreate(['name' => 'Efectivo'], ['is_active' => true]);
        $cardMethod = \App\Models\PaymentMethod::firstOrCreate(['name' => 'Tarjeta'], ['is_active' => true]);
        $creditNoteMethod = \App\Models\PaymentMethod::firstOrCreate(['name' => 'Nota de Crédito'], ['is_active' => true]); // Ensure this name matches frontend logic

        // Create Journals
        $invoiceJournal = \App\Models\Journal::firstOrCreate(
            ['code' => 'F004'],
            ['name' => 'Factura Electrónica', 'type' => 'sale', 'company_id' => $company->id, 'warehouse_id' => $warehouse->id, 'document_type_code' => '01']
        );
        $receiptJournal = \App\Models\Journal::firstOrCreate(
            ['code' => 'B004'],
            ['name' => 'Boleta de Venta', 'type' => 'sale', 'company_id' => $company->id, 'warehouse_id' => $warehouse->id, 'document_type_code' => '03']
        );
        $creditNoteJournal = \App\Models\Journal::firstOrCreate(
            ['code' => 'BC04'],
            ['name' => 'Nota de Crédito', 'type' => 'sale', 'company_id' => $company->id, 'warehouse_id' => $warehouse->id, 'document_type_code' => '07']
        );

        // Create POS Config
        $posConfig = \App\Models\PosConfig::firstOrCreate(
            ['name' => 'Test POS'],
            [
                'warehouse_id' => $warehouse->id,
                'company_id' => $company->id,
                'tax_id' => $tax->id,
                'apply_tax' => true,
                'prices_include_tax' => true,
                'is_active' => true
            ]
        );
        // Attach journals
        $posConfig->journals()->syncWithoutDetaching([
            $invoiceJournal->id => ['document_type' => 'invoice', 'is_default' => false],
            $receiptJournal->id => ['document_type' => 'receipt', 'is_default' => true],
            $creditNoteJournal->id => ['document_type' => 'credit_note', 'is_default' => false]
        ]);

        // Create Session
        $session = \App\Models\PosSession::create([
            'user_id' => $user->id,
            'pos_config_id' => $posConfig->id,
            'opening_balance' => 100,
            'status' => 'opened',
            'opened_at' => now(),
        ]);

        $this->info("✅ Setup complete. Session ID: {$session->id}");

        // 2. CASE 1: STANDARD SALE
        $this->info("\n[2/6] Case 1: Standard Sale (2 items)...");
        $posController = new \App\Http\Controllers\Pos\PosController();

        $saleStart1 = now();
        $beforeSaleId1 = (int) (\App\Models\Sale::max('id') ?? 0);

        $cart1 = [
            [
                'product_id' => $product->id,
                'name' => 'Test Product',
                'qty' => 2,
                'price' => 100, // Price includes tax
                'subtotal' => 200,
            ]
        ];
        $payments1 = [
            ['payment_method_id' => $cashMethod->id, 'amount' => 200]
        ];

        $request1 = new \Illuminate\Http\Request();
        $request1->merge([
            'journal_id' => $receiptJournal->id,
            'cart' => json_encode($cart1),
            'client_id' => $partner->id,
            'total' => 200,
            'payments' => json_encode($payments1)
        ]);

        try {
            $response1 = $posController->processPayment($request1, $session);
            $sale1 = \App\Models\Sale::query()
                ->where('pos_session_id', $session->id)
                ->where('id', '>', $beforeSaleId1)
                ->orderByDesc('id')
                ->first();

            if (! $sale1) {
                $this->error('❌ Sale 1 was not created.');
                $this->error('Controller response: ' . (is_object($response1) ? get_class($response1) : gettype($response1)));
                return;
            }

            if (abs((float) $sale1->total - 200) < 0.01 && $sale1->payment_status === 'paid') {
                $this->info("✅ Sale 1 created successfully. ID: {$sale1->id}, Total: {$sale1->total}");
            } else {
                $this->error("❌ Sale 1 failed validation. ID: {$sale1->id}, Total: {$sale1->total}, Status: {$sale1->payment_status}");
                return;
            }
        } catch (\Exception $e) {
            $this->error("❌ Error in Sale 1: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return;
        }

        // 3. CASE 2: SUBSCRIPTION SALE (Qty > 1)
        $this->info("\n[3/6] Case 2: Subscription Sale (Qty 3)...");

        $saleStart2 = now();
        $beforeSaleId2 = (int) (\App\Models\Sale::max('id') ?? 0);

        $subStart = now()->addDays(2)->startOfDay();
        $subEnd = $subStart->copy()->addMonthsNoOverflow(1)->subDay();

        $cart2 = [
            [
                'product_id' => $subProduct->id,
                'name' => 'Membership 1 Month',
                'qty' => 2,
                'price' => 150,
                'subtotal' => 300,
                'subscription_start_date' => $subStart->toDateString(),
                'subscription_end_date' => $subEnd->toDateString(),
            ]
        ];
        $payments2 = [
            ['payment_method_id' => $cardMethod->id, 'amount' => 300]
        ];

        $request2 = new \Illuminate\Http\Request();
        $request2->merge([
            'journal_id' => $receiptJournal->id,
            'cart' => json_encode($cart2),
            'client_id' => $partner->id, // Client mandatory for subs
            'total' => 300,
            'payments' => json_encode($payments2)
        ]);

        try {
            $response2 = $posController->processPayment($request2, $session);
            $sale2 = \App\Models\Sale::query()
                ->where('pos_session_id', $session->id)
                ->where('id', '>', $beforeSaleId2)
                ->orderByDesc('id')
                ->first();

            if (! $sale2) {
                $this->error('❌ Sale 2 was not created.');
                $this->error('Controller response: ' . (is_object($response2) ? get_class($response2) : gettype($response2)));
                return;
            }

            // Check subscriptions
            $subs = \App\Models\MembershipSubscription::where('partner_id', $partner->id)
                ->where('created_at', '>=', $saleStart2)
                ->get();

            $this->info("ℹ️  Subscriptions created in this batch: " . $subs->count());

            if ($subs->count() == 2) {
                $sub1 = $subs->sortBy('start_date')->values()->get(0);
                $sub2 = $subs->sortBy('start_date')->values()->get(1);

                $this->info("✅ Correctly created 2 consecutive subscriptions for Qty 2.");

                if ($sub1->start_date->format('Y-m-d') === $subStart->format('Y-m-d') && $sub1->end_date->format('Y-m-d') === $subEnd->format('Y-m-d')) {
                    $this->info("✅ Subscription #1 dates match custom range.");
                } else {
                    $this->warn("⚠️  Subscription #1 mismatch. Got {$sub1->start_date->format('Y-m-d')} -> {$sub1->end_date->format('Y-m-d')}");
                }

                $expectedStart2 = $subEnd->copy()->addDay();
                $expectedEnd2 = $expectedStart2->copy()->addMonthsNoOverflow(1)->subDay();
                if ($sub2->start_date->format('Y-m-d') === $expectedStart2->format('Y-m-d') && $sub2->end_date->format('Y-m-d') === $expectedEnd2->format('Y-m-d')) {
                    $this->info("✅ Subscription #2 dates are consecutive and correct.");
                } else {
                    $this->warn("⚠️  Subscription #2 mismatch. Got {$sub2->start_date->format('Y-m-d')} -> {$sub2->end_date->format('Y-m-d')}");
                }
            } else {
                $this->warn("⚠️  Created {$subs->count()} subscriptions. Expected 2 consecutive subscriptions.");
            }

            $this->info("✅ Sale 2 created. ID: {$sale2->id}, Total: {$sale2->total}");
        } catch (\Exception $e) {
            $this->error("❌ Error in Sale 2: " . $e->getMessage());
            return;
        }

        // 4. CASE 3: CREDIT NOTE GENERATION
        $this->info("\n[4/6] Case 3: Generating Credit Note from Sale 1...");
        $refundController = new \App\Http\Controllers\Pos\PosRefundController();

        // Return 1 item from Sale 1 (Price 100)
        $returnItems = [
            [
                'product_product_id' => $product->id,
                'quantity' => 1
            ]
        ];

        try {
            $previewReq = new \Illuminate\Http\Request();
            $previewReq->merge([
                'origin_sale_id' => $sale1->id,
                'return_items' => $returnItems,
                'sale_items' => [],
            ]);

            $previewRes = $refundController->preview($previewReq, $session);
            $previewData = method_exists($previewRes, 'getData') ? $previewRes->getData(true) : null;
            $toRefund = (float) ($previewData['to_refund'] ?? 0);

            if ($toRefund <= 0) {
                $this->error('❌ Refund preview returned invalid amount.');
                $this->error('Preview data: ' . json_encode($previewData));
                return;
            }

            $beforeSaleId3 = (int) (\App\Models\Sale::max('id') ?? 0);

            $request3 = new \Illuminate\Http\Request();
            $request3->merge([
                'origin_sale_id' => $sale1->id,
                'return_items' => $returnItems,
                'sale_items' => [],
                'refund_amount' => $toRefund,
                'refund_payment_method_id' => $creditNoteMethod->id,
            ]);

            $response3 = $refundController->process($request3, $session);

            $creditNote = \App\Models\Sale::query()
                ->where('id', '>', $beforeSaleId3)
                ->where('pos_session_id', $session->id)
                ->where('original_sale_id', $sale1->id)
                ->orderByDesc('id')
                ->first();

            if (! $creditNote) {
                $this->error('❌ Credit Note was not created.');
                $this->error('Controller response: ' . (is_object($response3) ? get_class($response3) : gettype($response3)));
                $this->error('Preview data: ' . json_encode($previewData));
                return;
            }

            $this->info("✅ Credit Note created. ID: {$creditNote->id}, Total: {$creditNote->total}, Doc: {$creditNote->serie}-{$creditNote->correlative}");
        } catch (\Exception $e) {
            $this->error("❌ Error in Refund: " . $e->getMessage());
            return;
        }

        // 5. CASE 4: PAYMENT WITH CREDIT NOTE
        $this->info("\n[5/6] Case 4: Payment with Credit Note (Partial)...");

        $creditNoteTotal = round((float) $creditNote->total, 2);
        $useCredit = min(80.00, $creditNoteTotal);
        $cashPart = 50.00;
        $sale4Total = round($useCredit + $cashPart, 2);

        // Buy item worth sale4Total. Use credit note + cash.
        $cart4 = [
            [
                'product_id' => $product->id,
                'name' => 'New Product',
                'qty' => 1,
                'price' => $sale4Total,
                'subtotal' => $sale4Total,
            ]
        ];

        // Payments array
        $payments4 = [
            ['payment_method_id' => $creditNoteMethod->id, 'amount' => $useCredit],
            ['payment_method_id' => $cashMethod->id, 'amount' => $cashPart]
        ];

        // Credit Note Info Payload
        $creditNoteInfo = [
            'id' => $creditNote->id,
            'document' => $creditNote->serie . '-' . $creditNote->correlative,
            'amount_used' => $useCredit
        ];

        $request4 = new \Illuminate\Http\Request();
        $request4->merge([
            'journal_id' => $receiptJournal->id,
            'cart' => json_encode($cart4),
            'client_id' => $partner->id,
            'total' => $sale4Total,
            'payments' => json_encode($payments4),
            'credit_note_info' => json_encode($creditNoteInfo)
        ]);

        try {
            $beforeSaleId4 = (int) (\App\Models\Sale::max('id') ?? 0);
            $response4 = $posController->processPayment($request4, $session);

            $sale4 = \App\Models\Sale::query()
                ->where('pos_session_id', $session->id)
                ->where('id', '>', $beforeSaleId4)
                ->orderByDesc('id')
                ->first();

            if (! $sale4) {
                $this->error('❌ Sale 4 was not created.');
                $this->error('Controller response: ' . (is_object($response4) ? get_class($response4) : gettype($response4)));
                return;
            }

            // Verify DB Record
            $paymentRecord = \App\Models\PosSessionPayment::where('sale_id', $sale4->id)
                ->where('reference_sale_id', $creditNote->id)
                ->first();

            if ($paymentRecord && abs((float) $paymentRecord->amount - $useCredit) < 0.01) {
                $this->info("✅ Payment successfully linked to Credit Note ID {$creditNote->id}");
            } else {
                $this->error("❌ Payment NOT linked to Credit Note correctly.");
                $allPayments = \App\Models\PosSessionPayment::where('sale_id', $sale4->id)->get();
                $this->info("Payments found: " . $allPayments->toJson());
            }

            // Verify Balance Calculation
            $requestBalance = new \Illuminate\Http\Request();
            $balanceResponse = $posController->getCreditNotes($requestBalance, $session, $partner);
            $notes = $balanceResponse->getData();

            $expectedBalance = round(max(0, $creditNoteTotal - $useCredit), 2);
            $found = false;
            foreach ($notes as $n) {
                if ($n->id == $creditNote->id) {
                    $found = true;
                    if (abs((float) $n->balance - $expectedBalance) < 0.01) {
                        $this->info("✅ Credit Note balance is correct ({$n->balance}).");
                    } else {
                        $this->error("❌ Credit Note balance incorrect. Expected {$expectedBalance}, Got {$n->balance}");
                    }
                }
            }
            if (! $found) {
                $this->warn('⚠️  Credit Note not returned in available list. Balance may be 0.');
            }
        } catch (\Exception $e) {
            $this->error("❌ Error in Payment with Credit Note: " . $e->getMessage());
            return;
        }

        // 6. CLOSE SESSION
        $this->info("\n[6/6] Closing Session...");

        // Calculate totals
        // Sales: 200 + 450 + 150 = 800
        // Refund: -100 (Technically a sale with negative impact or separate doc, depends on accounting)
        // Payments:
        // Cash: 200 (S1) + 50 (S4) = 250
        // Card: 450 (S2)
        // Credit Note: 100 (S4)

        // Note: Refunds usually take money OUT if cash refund, or create a Credit Note document.
        // In our case, we created a Credit Note document (Sale type 07). It doesn't impact cash drawer unless we paid cash out.
        // We selected "Credit Note" method for refund, so no cash out.

        $requestClose = new \Illuminate\Http\Request();
        $totalsByMethod = \App\Models\PosSessionPayment::query()
            ->where('pos_session_id', $session->id)
            ->whereNotNull('sale_id')
            ->where('amount', '>', 0)
            ->selectRaw('payment_method_id, SUM(amount) as total')
            ->groupBy('payment_method_id')
            ->get()
            ->map(function ($row) {
                return [
                    'payment_method_id' => (int) $row->payment_method_id,
                    'amount' => (float) $row->total,
                ];
            })
            ->values()
            ->all();

        $netCash = (float) \App\Models\PosSessionPayment::query()
            ->where('pos_session_id', $session->id)
            ->whereNotNull('sale_id')
            ->where('payment_method_id', $cashMethod->id)
            ->sum('amount');

        $closingBalance = round(((float) $session->opening_balance) + $netCash, 2);

        $requestClose->merge([
            'closing_balance' => $closingBalance,
            'closing_note' => 'Test Close',
            'payments' => $totalsByMethod,
        ]);

        try {
            $posController->storeClose($requestClose, $session);
            $session->refresh();
            if ($session->status == 'closed') {
                $this->info("✅ Session closed successfully.");
            } else {
                $this->error("❌ Session close failed.");
            }
        } catch (\Exception $e) {
            $this->error("❌ Error closing session: " . $e->getMessage());
        }

        $this->info("\n🎉 TEST COMPLETE!");
    }
}
