@extends('layouts.app')

@section('title', 'Reporte de Productos y Rentabilidad')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
    <div>
        <h3 class="fw-bold mb-1">Productos Más Vendidos y Rentabilidad</h3>
        <p class="text-muted small mb-0">Análisis de margen y rotación de productos en <strong>{{ $currentBusiness->name }}</strong>.</p>
    </div>
</div>

{{-- Pestañas de Reportes --}}
<ul class="nav nav-pills mb-4 gap-2">
    <li class="nav-item">
        <a class="nav-link rounded-pill px-4 {{ request()->routeIs('reports.index') ? 'active' : 'bg-white text-secondary shadow-sm' }}" href="{{ route('reports.index') }}">
            <i class="bi bi-grid me-1"></i> Centro
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link rounded-pill px-4 {{ request()->routeIs('reports.sales') ? 'active' : 'bg-white text-secondary shadow-sm' }}" href="{{ route('reports.sales') }}">
            <i class="bi bi-graph-up-arrow me-1"></i> Ventas
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link rounded-pill px-4 {{ request()->routeIs('reports.inventory') ? 'active' : 'bg-white text-secondary shadow-sm' }}" href="{{ route('reports.inventory') }}">
            <i class="bi bi-boxes me-1"></i> Valoración Inventario
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link rounded-pill px-4 {{ request()->routeIs('reports.top-products') ? 'active' : 'bg-white text-secondary shadow-sm' }}" href="{{ route('reports.top-products') }}">
            <i class="bi bi-trophy me-1"></i> Top Productos
        </a>
    </li>
</ul>

{{-- Tarjetas KPI --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card card-custom p-3 bg-white h-100 border-0 shadow-sm">
            <span class="text-muted small fw-semibold text-uppercase">Unidades Vendidas</span>
            <h3 class="fw-bold mb-0 mt-1 text-primary">{{ number_format($total_items_sold, 0, ',', '.') }}</h3>
            <small class="text-muted">Artículos despachados</small>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card card-custom p-3 bg-white h-100 border-0 shadow-sm">
            <span class="text-muted small fw-semibold text-uppercase">Ingresos Totales</span>
            <h3 class="fw-bold mb-0 mt-1 text-success">${{ number_format($total_revenue, 0, ',', '.') }}</h3>
            <small class="text-muted">Recaudación bruta</small>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card card-custom p-3 bg-white h-100 border-0 shadow-sm">
            <span class="text-muted small fw-semibold text-uppercase">Costo Mercancía (COGS)</span>
            <h3 class="fw-bold mb-0 mt-1 text-danger">${{ number_format($total_cogs, 0, ',', '.') }}</h3>
            <small class="text-muted">Costo de adquisición</small>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card card-custom p-3 bg-white h-100 border-0 shadow-sm">
            <span class="text-muted small fw-semibold text-uppercase">Utilidad Bruta</span>
            <h3 class="fw-bold mb-0 mt-1 text-dark">${{ number_format($total_gross_profit, 0, ',', '.') }}</h3>
            <small class="text-muted">Margen promedio: <strong>{{ $overall_margin_percent }}%</strong></small>
        </div>
    </div>
</div>

{{-- Filtro de Fechas --}}
<div class="card card-custom p-3 bg-white mb-4 border-0 shadow-sm">
    <form method="GET" action="{{ route('reports.top-products') }}" class="row g-2 align-items-center">
        <div class="col-md-5">
            <label for="date_from" class="form-label small text-muted mb-1">Desde</label>
            <input type="date" id="date_from" name="date_from" class="form-control" value="{{ $date_from }}">
        </div>
        <div class="col-md-5">
            <label for="date_to" class="form-label small text-muted mb-1">Hasta</label>
            <input type="date" id="date_to" name="date_to" class="form-control" value="{{ $date_to }}">
        </div>
        <div class="col-md-2 d-flex align-items-end pt-4">
            <button type="submit" class="btn btn-primary rounded-pill w-100 fw-semibold">
                <i class="bi bi-funnel me-1"></i> Filtrar
            </button>
        </div>
    </form>
</div>

{{-- Tabla de Ranking y Rentabilidad --}}
<div class="card card-custom p-0 bg-white border-0 shadow-sm overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">#</th>
                    <th>Producto</th>
                    <th class="text-center">Unid. Vendidas</th>
                    <th class="text-end">Costo Unit.</th>
                    <th class="text-end">Precio Venta</th>
                    <th class="text-end">Ingresos</th>
                    <th class="text-end">Costo Total</th>
                    <th class="text-end pe-4">Utilidad Bruta</th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $idx => $prod)
                    <tr>
                        <td class="ps-4">
                            <span class="badge bg-light text-dark border rounded-circle" style="width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center;">
                                {{ $idx + 1 }}
                            </span>
                        </td>
                        <td>
                            <div class="fw-bold text-dark">{{ $prod->name }}</div>
                            <small class="text-muted">SKU: {{ $prod->sku }}</small>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-primary-subtle text-primary fw-bold px-3 py-1 rounded-pill">
                                {{ $prod->total_quantity }} unid.
                            </span>
                        </td>
                        <td class="text-end text-muted">${{ number_format($prod->cost_price, 0, ',', '.') }}</td>
                        <td class="text-end text-muted">${{ number_format($prod->sale_price, 0, ',', '.') }}</td>
                        <td class="text-end fw-semibold text-success">${{ number_format($prod->total_revenue, 0, ',', '.') }}</td>
                        <td class="text-end text-danger">${{ number_format($prod->cogs, 0, ',', '.') }}</td>
                        <td class="text-end pe-4">
                            <div class="fw-bold text-dark">${{ number_format($prod->gross_profit, 0, ',', '.') }}</div>
                            <small class="text-muted">{{ $prod->margin_percent }}% margen</small>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-bar-chart fs-1 d-block mb-2 text-secondary opacity-50"></i>
                            No hay ventas registradas en el período seleccionado.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
