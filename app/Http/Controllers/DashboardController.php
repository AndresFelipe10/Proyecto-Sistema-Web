<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Show application dashboard.
     */
    public function index(Request $request): View
    {
        $businessId = (int) (session('current_business_id') ?? $request->user()->current_business_id);
        $isAdmin = (bool) $request->user()->isCurrentAdmin();

        $metrics = $this->dashboardService->getMetrics($businessId, $isAdmin);

        return view('dashboard', array_merge([
            'user' => $request->user(),
            'isAdmin' => $isAdmin,
        ], $metrics));
    }

    /**
     * Return dashboard metrics as JSON for API consumers.
     * Enforces role-based financial masking.
     */
    public function api(Request $request): JsonResponse
    {
        $businessId = (int) (session('current_business_id') ?? $request->user()->current_business_id);
        $isAdmin = (bool) $request->user()->isCurrentAdmin();

        $metrics = $this->dashboardService->getMetrics($businessId, $isAdmin);

        return response()->json([
            'success' => true,
            'business_id' => $businessId,
            'is_admin' => $isAdmin,
            'metrics' => $metrics,
        ]);
    }
}

