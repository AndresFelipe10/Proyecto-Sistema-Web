<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\DashboardService;
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

        $metrics = $this->dashboardService->getMetrics($businessId);

        return view('dashboard', array_merge([
            'user' => $request->user(),
        ], $metrics));
    }
}
