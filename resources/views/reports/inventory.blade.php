@extends('layouts.app')

@section('title', 'Reporte de Valoración de Inventario')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
    <div>
        <h3 class="fw-bold mb-1">Valoración de Inventario</h3>
        <p class="text-muted small mb-0">Capital inmovilizado en existencias y proyección comercial para <strong>{{ $currentBusiness->name }}</strong>.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('reports.inventory', array_merge(request()->all(), ['export' => 'csv'])) }}" class="btn btn-outline-success rounded-pill px-4 fw-semibold shadow-sm">
            <i class="bi bi-file-earmark-excel me-1"></i> Exportar a CSV
        </a>
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
            <span class="text-muted small fw-semibold text-uppercase">Valoración al Costo</span>
            <h3 class="fw-bold mb-0 mt-1 text-primary">${{ number_format($total_cost_valuation, 0, ',', '.') }}</h3>
            <small class="text-muted">Capital invertido en stock</small>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card card-custom p-3 bg-white h-100 border-0 shadow-sm">
            <span class="text-muted small fw-semibold text-uppercase">Valoración Comercial</span>
            <h3 class="fw-bold mb-0 mt-1 text-success">${{ number_format($total_retail_valuation, 0, ',', '.') }}</h3>
            <small class="text-muted">Ingreso potencial de venta</small>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card card-custom p-3 bg-white h-100 border-0 shadow-sm">
            <span class="text-muted small fw-semibold text-uppercase">Ganancia Bruta Potencial</span>
            <h3 class="fw-bold mb-0 mt-1 text-dark">${{ number_format($total_potential_profit, 0, ',', '.') }}</h3>
            <small class="text-muted">Margen proyectado: <strong>{{ $potential_margin_percent }}%</strong></small>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card card-custom p-3 bg-white h-100 border-0 shadow-sm">
            <span class="text-muted small fw-semibold text-uppercase">Unidades Totales</span>
            <h3 class="fw-bold mb-0 mt-1 text-info">{{ number_format($total_units, 0, ',', '.') }}</h3>
            <small class="text-muted">{{ $total_products }} productos activos</small>
        </div>
    </div>
</div>

{{-- Filtros --}}
<div class="card card-custom p-3 bg-white mb-4 border-0 shadow-sm">
    <form method="GET" action="{{ route('reports.inventory') }}" class="row g-2 align-items-center">
        <div class="col-md-5">
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="search" class="form-control border-start-0" placeholder="Buscar por producto o SKU..." value="{{ $search }}">
            </div>
        </div>
        <div class="col-md-4">
            <select name="category_id" class="form-select">
                <option value="">Todas las categorías</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ (string)$selected_category_id === (string)$cat->id ? 'selected' : '' }}>
                        {{ $cat->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary rounded-pill flex-grow-1 fw-semibold">
                <i class="bi bi-funnel me-1"></i> Filtrar
            </button>
            @if($search || $selected_category_id)
                <a href="{{ route('reports.inventory') }}" class="btn btn-light rounded-pill text-secondary" title="Limpiar"><i class="bi bi-x-lg"></i></a>
            @endif
        </div>
    </form>
</div>

{{-- Tabla de Valoración --}}
<div class="card card-custom p-0 bg-white border-0 shadow-sm overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">Producto</th>
                    <th>Categoría</th>
                    <th class="text-center">Stock</th>
                    <th class="text-end">Costo Unitario</th>
                    <th class="text-end">Precio Venta</th>
                    <th class="text-end">Valor Costo</th>
                    <th class="text-end">Valor Venta</th>
                    <th class="text-end pe-4">Ganancia Estimada</th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $prod)
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold text-dark">{{ $prod->name }}</div>
                            <small class="text-muted">SKU: {{ $prod->sku }}</small>
                        </td>
                        <td>
                            @if($prod->category)
                                <span class="badge bg-light text-dark border">{{ $prod->category->name }}</span>
                            @else
                                <span class="text-muted small">Sin categoría</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <span class="fw-bold {{ $prod->stock <= $prod->min_stock ? 'text-warning' : 'text-dark' }}">
                                {{ $prod->stock }}
                            </span>
                        </td>
                        <td class="text-end text-muted">${{ number_format($prod->cost_price, 0, ',', '.') }}</td>
                        <td class="text-end text-muted">${{ number_format($prod->sale_price, 0, ',', '.') }}</td>
                        <td class="text-end fw-semibold text-primary">${{ number_format($prod->cost_valuation, 0, ',', '.') }}</td>
                        <td class="text-end fw-semibold text-success">${{ number_format($prod->retail_valuation, 0, ',', '.') }}</td>
                        <td class="text-end pe-4">
                            <span class="fw-bold text-dark">${{ number_format($prod->potential_profit, 0, ',', '.') }}</span>
                            <div class="small text-muted">{{ $prod->margin_percent }}% margen</div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-boxes fs-1 d-block mb-2 text-secondary opacity-50"></i>
                            No se encontraron productos en el inventario.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
