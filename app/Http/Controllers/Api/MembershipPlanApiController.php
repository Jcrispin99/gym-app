<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MembershipPlan;
use Illuminate\Http\Request;

class MembershipPlanApiController extends Controller
{
    /**
     * Get active membership plans for POS
     * Filters by company from authenticated user's POS session
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $plans = MembershipPlan::with(['productProduct.template'])
            ->where('is_active', true)
            ->whereNotNull('product_product_id') // Only plans with associated products
            ->orderBy('price')
            ->get()
            ->map(function ($plan) {
                $product = $plan->productProduct;
                $template = $product?->template;

                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'description' => $plan->description,
                    'duration_days' => $plan->duration_days,
                    'price' => (float) $plan->price,
                    'product_product_id' => $plan->product_product_id,
                    'product_name' => $template?->name ?? "Plan: {$plan->name}",
                    'max_entries_per_month' => $plan->max_entries_per_month,
                    'max_entries_per_day' => $plan->max_entries_per_day,
                    'time_restricted' => (bool) $plan->time_restricted,
                    'allowed_time_start' => $plan->allowed_time_start?->format('H:i'),
                    'allowed_time_end' => $plan->allowed_time_end?->format('H:i'),
                    'allowed_days' => $plan->allowed_days,
                    'allows_freezing' => (bool) $plan->allows_freezing,
                    'max_freeze_days' => $plan->max_freeze_days,
                ];
            });

        return response()->json($plans);
    }
}
