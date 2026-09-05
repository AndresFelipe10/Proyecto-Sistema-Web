<?php

namespace App\Http\Controllers;

use App\Models\InventoryMovement;
use App\Services\Inventory\InventoryIntelligenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InventoryAlertController extends Controller
{
    protected InventoryIntelligenceService $intelligenceService;

    public function __construct(InventoryIntelligenceService $intelligenceService)
    {
        $this->intelligenceService = $intelligenceService;
    }

    /**
     * Display the smart inventory panel with deterministic alerts and replenishment recommendations.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', InventoryMovement::class);

        $businessId = (int) (session('current_business_id') ?? $request->user()->current_business_id);

        $analysis = $this->intelligenceService->getInventoryAnalysis($businessId, [
            'status' => $request->input('status', 'all'),
            'search' => $request->input('search'),
            'category_id' => $request->input('category_id'),
        ]);

        return view('inventory.alerts', $analysis);
    }
}
