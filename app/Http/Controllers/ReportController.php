<?php

namespace App\Http\Controllers;

use App\Services\Reports\ReportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ReportController extends Controller
{
    protected ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Reports Center / Hub overview.
     */
    public function index(Request $request): View
    {
        $businessId = (int) (session('current_business_id') ?? $request->user()->current_business_id);

        $salesData = $this->reportService->getSalesReport($businessId);
        $inventoryData = $this->reportService->getInventoryValuationReport($businessId);

        return view('reports.index', [
            'salesSummary' => $salesData,
            'inventorySummary' => $inventoryData,
        ]);
    }

    /**
     * Sales performance report with optional CSV export.
     */
    public function sales(Request $request): View|Response
    {
        $businessId = (int) (session('current_business_id') ?? $request->user()->current_business_id);

        $filters = $request->only(['date_from', 'date_to', 'payment_method', 'customer_id']);

        if ($request->query('export') === 'csv') {
            $csv = $this->reportService->exportSalesCsv($businessId, $filters);
            $filename = 'reporte_ventas_' . date('Ymd_His') . '.csv';

            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        }

        $data = $this->reportService->getSalesReport($businessId, $filters);

        return view('reports.sales', $data);
    }

    /**
     * Inventory valuation and margin report with optional CSV export.
     */
    public function inventory(Request $request): View|Response
    {
        $businessId = (int) (session('current_business_id') ?? $request->user()->current_business_id);

        $filters = $request->only(['category_id', 'search']);

        if ($request->query('export') === 'csv') {
            $csv = $this->reportService->exportInventoryCsv($businessId, $filters);
            $filename = 'reporte_inventario_' . date('Ymd_His') . '.csv';

            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        }

        $data = $this->reportService->getInventoryValuationReport($businessId, $filters);

        return view('reports.inventory', $data);
    }

    /**
     * Top selling products and profitability report.
     */
    public function topProducts(Request $request): View
    {
        $businessId = (int) (session('current_business_id') ?? $request->user()->current_business_id);

        $filters = $request->only(['date_from', 'date_to']);
        $data = $this->reportService->getTopProductsReport($businessId, $filters);

        return view('reports.top-products', $data);
    }
}
