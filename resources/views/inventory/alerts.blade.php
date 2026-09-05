@extends('layouts.app')

@section('title', 'Panel Inteligente de Inventario y Alertas')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
    <div>
        <h3 class="fw-bold mb-1">Panel Inteligente de Inventario</h3>
        <p class="text-muted small mb-0">Clasificación determinística de existencias y recomendaciones de reposición para <strong>{{ $currentBusiness->name }}</strong>.</p>
    </div>
    @can('create', App\Models\InventoryMovement::class)
        <a href="{{ route('inventory.create') }}" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm">
            <i class="bi bi-box-arrow-in-down me-1"></i> Registrar Entrada de Stock
        </a>
    @endcan
</div>

{{-- Pestañas de Navegación del Módulo de Inventario --}}
<ul class="nav nav-pills mb-4 gap-2">
    <li class="nav-item">
        <a class="nav-link rounded-pill px-4 {{ request()->routeIs('inventory.index') ? 'active' : 'bg-white text-secondary shadow-sm' }}" href="{{ route('inventory.index') }}">
            <i class="bi bi-clock-history me-1"></i> Movimientos de Inventario
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link rounded-pill px-4 {{ request()->routeIs('inventory.alerts') ? 'active' : 'bg-white text-secondary shadow-sm' }}" href="{{ route('inventory.alerts') }}">
            <i class="bi bi-shield-exclamation me-1"></i> Panel Inteligente & Alertas
            @if(($out_of_stock_count + $low_stock_count) > 0)
                <span class="badge bg-danger ms-1 rounded-pill">{{ $out_of_stock_count + $low_stock_count }}</span>
            @endif
        </a>
    </li>
</ul>

{{-- Tarjetas KPI de Estado de Inventario --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card card-custom p-3 bg-white h-100 border-0 shadow-sm">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Agotados</span>
                    <h3 class="fw-bold mb-0 mt-1 {{ $out_of_stock_count > 0 ? 'text-danger' : 'text-dark' }}">
                        {{ $out_of_stock_count }}
                    </h3>
                    <small class="text-muted">Stock = 0 unidades</small>
                </div>
                <div class="{{ $out_of_stock_count > 0 ? 'bg-danger-subtle text-danger' : 'bg-light text-muted' }} p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                    <i class="bi bi-x-circle-fill fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card card-custom p-3 bg-white h-100 border-0 shadow-sm">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Bajo Stock</span>
                    <h3 class="fw-bold mb-0 mt-1 {{ $low_stock_count > 0 ? 'text-warning' : 'text-dark' }}">
                        {{ $low_stock_count }}
                    </h3>
                    <small class="text-muted">Stock ≤ Stock Mínimo</small>
                </div>
                <div class="{{ $low_stock_count > 0 ? 'bg-warning-subtle text-warning' : 'bg-light text-muted' }} p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                    <i class="bi bi-exclamation-triangle-fill fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card card-custom p-3 bg-white h-100 border-0 shadow-sm">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Nivel Óptimo</span>
                    <h3 class="fw-bold mb-0 mt-1 text-success">
                        {{ $normal_count }}
                    </h3>
                    <small class="text-muted">Stock > Stock Mínimo</small>
                </div>
                <div class="bg-success-subtle text-success p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                    <i class="bi bi-check-circle-fill fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card card-custom p-3 bg-white h-100 border-0 shadow-sm">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Inversión Reposición</span>
                    <h3 class="fw-bold mb-0 mt-1 text-primary">
                        ${{ number_format($total_replenishment_cost, 0, ',', '.') }}
                    </h3>
                    <small class="text-muted">Costo estimado total</small>
                </div>
                <div class="bg-primary-subtle text-primary p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                    <i class="bi bi-cash-coin fs-4"></i>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Filtros --}}
<div class="card card-custom p-3 bg-white mb-4 border-0 shadow-sm">
    <form method="GET" action="{{ route('inventory.alerts') }}" class="row g-2 align-items-center">
        <div class="col-md-4">
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="search" class="form-control border-start-0"
                       placeholder="Buscar por producto o SKU..." value="{{ $filters['search'] }}">
            </div>
        </div>

        <div class="col-md-3">
            <select name="category_id" class="form-select">
                <option value="">Todas las categorías</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat->id }}" {{ $filters['category_id'] == $cat->id ? 'selected' : '' }}>
                        {{ $cat->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="col-md-3">
            <select name="status" class="form-select">
                <option value="all" {{ $filters['status'] === 'all' ? 'selected' : '' }}>Todos los estados</option>
                <option value="alert" {{ $filters['status'] === 'alert' ? 'selected' : '' }}>Solo alertas (Agotados + Bajos)</option>
                <option value="out_of_stock" {{ $filters['status'] === 'out_of_stock' ? 'selected' : '' }}>Agotados</option>
                <option value="low_stock" {{ $filters['status'] === 'low_stock' ? 'selected' : '' }}>Bajo Stock</option>
                <option value="normal" {{ $filters['status'] === 'normal' ? 'selected' : '' }}>Normal / Óptimo</option>
            </select>
        </div>

        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary rounded-pill flex-grow-1 fw-semibold">
                <i class="bi bi-funnel me-1"></i> Filtrar
            </button>
            @if ($filters['search'] || $filters['category_id'] || $filters['status'] !== 'all')
                <a href="{{ route('inventory.alerts') }}" class="btn btn-light rounded-pill text-secondary" title="Limpiar"><i class="bi bi-x-lg"></i></a>
            @endif
        </div>
    </form>
</div>

{{-- Tabla de Diagnóstico Inteligente --}}
<div class="card card-custom p-0 bg-white border-0 shadow-sm overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">Producto</th>
                    <th>Categoría</th>
                    <th class="text-center">Stock Actual / Mínimo</th>
                    <th class="text-center">Estado</th>
                    <th class="text-center">Déficit Mínimo</th>
                    <th class="text-center">Sugerido a Reponer</th>
                    <th class="text-end">Costo Estimado</th>
                    <th class="text-end pe-4">Acción</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($products as $prod)
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold text-dark">{{ $prod->name }}</div>
                            <small class="text-muted">SKU: {{ $prod->sku }}</small>
                        </td>
                        <td>
                            @if ($prod->category)
                                <span class="badge bg-light text-dark border">{{ $prod->category->name }}</span>
                            @else
                                <span class="text-muted small">Sin categoría</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <span class="fw-bold {{ $prod->stock <= 0 ? 'text-danger' : ($prod->stock <= $prod->min_stock ? 'text-warning' : 'text-dark') }}">
                                {{ $prod->stock }}
                            </span>
                            <span class="text-muted small">/ {{ $prod->min_stock }}</span>
                        </td>
                        <td class="text-center">
                            @if ($prod->inventory_status === 'out_of_stock')
                                <span class="badge bg-danger rounded-pill px-3 py-1">
                                    <i class="bi bi-x-circle me-1"></i> Agotado
                                </span>
                            @elseif ($prod->inventory_status === 'low_stock')
                                <span class="badge bg-warning text-dark rounded-pill px-3 py-1">
                                    <i class="bi bi-exclamation-triangle me-1"></i> Bajo Stock
                                </span>
                            @else
                                <span class="badge bg-success rounded-pill px-3 py-1">
                                    <i class="bi bi-check-circle me-1"></i> Normal
                                </span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if ($prod->deficit > 0)
                                <span class="fw-semibold text-danger">+{{ $prod->deficit }} unid.</span>
                            @else
                                <span class="text-muted small">0 unid.</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if ($prod->suggested_qty > 0)
                                <span class="badge bg-primary-subtle text-primary fw-bold px-3 py-1 rounded-pill">
                                    {{ $prod->suggested_qty }} unid.
                                </span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td class="text-end fw-semibold text-dark">
                            @if ($prod->estimated_replenishment_cost > 0)
                                ${{ number_format($prod->estimated_replenishment_cost, 0, ',', '.') }}
                            @else
                                <span class="text-muted small">$0</span>
                            @endif
                        </td>
                        <td class="text-end pe-4">
                            @if ($prod->inventory_status !== 'normal')
                                @can('create', App\Models\InventoryMovement::class)
                                    <a href="{{ route('inventory.create', ['product_id' => $prod->id]) }}"
                                       class="btn btn-sm btn-outline-primary rounded-pill px-3"
                                       title="Registrar entrada de inventario">
                                        <i class="bi bi-plus-lg me-1"></i> Reponer
                                    </a>
                                @endcan
                            @else
                                <span class="text-muted small"><i class="bi bi-check2 text-success"></i> Al día</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-inboxes fs-1 d-block mb-2 text-secondary opacity-50"></i>
                            No se encontraron productos con los criterios seleccionados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
