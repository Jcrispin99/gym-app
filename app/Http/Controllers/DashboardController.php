<?php

namespace App\Http\Controllers;

use App\Models\Productable;
use App\Models\ProductProduct;
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $from = $validated['from'] ?? now()->startOfMonth()->toDateString();
        $to = $validated['to'] ?? now()->toDateString();

        $fromDate = Carbon::createFromFormat('Y-m-d', $from)->startOfDay();
        $toDate = Carbon::createFromFormat('Y-m-d', $to)->endOfDay();

        // --- Overall Stats (Filtered by Date) ---
        $salesQuery = Sale::query()
            ->whereBetween('date', [$fromDate, $toDate])
            ->where('status', 'posted');

        $salesCount = (int) (clone $salesQuery)->count();
        $salesTotal = (float) (clone $salesQuery)->sum('total');
        $averageTicket = $salesCount > 0 ? round($salesTotal / $salesCount, 2) : 0.0;

        // Customers unique count in period
        $customersCount = (int) (clone $salesQuery)->distinct('partner_id')->count('partner_id');

        // Unpaid voices in period
        $unpaidTotal = (float) Sale::query()
            ->whereBetween('date', [$fromDate, $toDate])
            ->where('status', 'posted')
            ->where('payment_status', 'unpaid')
            ->sum('total');

        // --- Today's Stats ---
        $todayQuery = Sale::query()
            ->whereDate('date', Carbon::today())
            ->where('status', 'posted');

        $todaySalesTotal = (float) (clone $todayQuery)->sum('total');
        $todayOrdersCount = (int) (clone $todayQuery)->count();
        $todayCustomersCount = (int) (clone $todayQuery)->distinct('partner_id')->count('partner_id');

        // --- Recent Orders ---
        $recentOrders = Sale::with('partner')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn ($sale) => [
                'id' => $sale->id,
                'serie_correlative' => $sale->serie.'-'.$sale->correlative,
                'date_formatted' => $sale->date->format('d M Y, H:i:s'),
                'status' => $sale->payment_status === 'paid' ? 'Paid' : ($sale->payment_status === 'unpaid' ? 'Pending' : ucfirst($sale->payment_status)),
                'status_class' => match ($sale->payment_status) {
                    'paid' => 'text-emerald-500',
                    'unpaid' => 'label-pending',
                    default => 'text-gray-600'
                },
                'total' => (float) $sale->total,
                'customer_name' => $sale->partner ? $sale->partner->first_name.' '.$sale->partner->last_name : 'Cliente General',
                'customer_email' => $sale->partner?->email ?? '',
                'image_url' => null, // Placeholder or specific logic
            ]);

        // --- Stock Threshold ---
        // Get products with stock < 5 optimized
        $latestInventoryIds = \App\Models\Inventory::query()
            ->select(DB::raw('MAX(id) as id'))
            ->groupBy('product_product_id')
            ->get()
            ->pluck('id');

        $lowStockInventories = \App\Models\Inventory::query()
            ->whereIn('id', $latestInventoryIds)
            ->where('quantity_balance', '<', 5)
            ->where('quantity_balance', '>=', 0)
            ->with(['productProduct.product', 'productProduct.attributeValues'])
            ->limit(5)
            ->get();

        $lowStockProducts = $lowStockInventories->map(function ($inv) {
            $p = $inv->productProduct;

            return [
                'id' => $p->id,
                'name' => $p->display_name ?? 'Producto',
                'sku' => $p->sku,
                'price' => (float) $p->price,
                'stock' => $inv->quantity_balance,
                'image_url' => null,
            ];
        })->values();

        // --- Top Selling Products ---
        $bestSellersAgg = Productable::query()
            ->selectRaw('productables.product_product_id, SUM(productables.quantity) as quantity, SUM(productables.total) as total')
            ->where('productable_type', Sale::class)
            ->join('sales', 'sales.id', '=', 'productables.productable_id')
            ->whereBetween('sales.date', [$fromDate, $toDate])
            ->where('sales.status', 'posted')
            ->groupBy('productables.product_product_id')
            ->orderByDesc(DB::raw('SUM(productables.total)'))
            ->limit(5)
            ->get();

        $productIds = $bestSellersAgg->pluck('product_product_id')->unique();
        $products = ProductProduct::with('product', 'attributeValues')->whereIn('id', $productIds)->get()->keyBy('id');

        $topSellingProducts = $bestSellersAgg->map(function ($row) use ($products) {
            $p = $products->get($row->product_product_id);

            return [
                'name' => $p?->display_name ?? 'Producto',
                'sku' => $p?->sku ?? '',
                'total_sold' => (float) $row->total,
                'qty_sold' => (float) $row->quantity,
                'image_url' => null,
            ];
        });

        // --- Top Customers ---
        $topCustomers = Sale::query()
            ->selectRaw('partner_id, count(*) as orders_count, sum(total) as total_spent')
            ->whereBetween('date', [$fromDate, $toDate])
            ->where('status', 'posted')
            ->whereNotNull('partner_id')
            ->groupBy('partner_id')
            ->orderByDesc('total_spent')
            ->with('partner')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->partner_id,
                'name' => $row->partner ? $row->partner->first_name.' '.$row->partner->last_name : 'Unknown',
                'email' => $row->partner?->email ?? '',
                'orders_count' => $row->orders_count,
                'total_spent' => (float) $row->total_spent,
            ]);

        // --- Charts Data ---
        $salesChartData = Sale::query()
            ->selectRaw('DATE(date) as day, sum(total) as total')
            ->whereBetween('date', [$fromDate, $toDate])
            ->where('status', 'posted')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($r) => ['date' => $r->day, 'value' => (float) $r->total]);

        $visitorsChartData = \App\Models\Attendance::query()
            ->selectRaw('DATE(check_in_time) as day, count(*) as count')
            ->whereBetween('check_in_time', [$fromDate, $toDate])
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($r) => ['date' => $r->day, 'value' => (int) $r->count]);

        return Inertia::render('Dashboard', [
            'filters' => [
                'from' => $from,
                'to' => $to,
            ],
            'overall' => [
                'total_sales' => $salesTotal,
                'total_orders' => $salesCount,
                'total_customers' => $customersCount,
                'average_ticket' => $averageTicket,
                'unpaid_invoices' => $unpaidTotal,
            ],
            'today' => [
                'total_sales' => $todaySalesTotal,
                'total_orders' => $todayOrdersCount,
                'total_customers' => $todayCustomersCount,
            ],
            'recent_orders' => $recentOrders,
            'stock_threshold' => $lowStockProducts,
            'top_products' => $topSellingProducts,
            'top_customers' => $topCustomers,
            'charts' => [
                'sales' => $salesChartData,
                'visitors' => $visitorsChartData,
            ],
        ]);
    }
}
