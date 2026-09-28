@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="row g-4">
    {{-- Banner de Bienvenida y Datos del Emprendimiento --}}
    <div class="col-12">
        <div class="card card-custom p-4 bg-white border-0 shadow-sm">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <h2 class="h4 fw-bold mb-1 text-dark">¡Hola, {{ $user->name }}!</h2>
                    <p class="text-muted mb-0">
                        Gestionando: 
                        <strong class="text-primary fs-6">{{ $currentBusiness->name }}</strong>
                        @if ($currentBusiness->nit)
                            <span class="badge bg-light text-dark ms-2 border">NIT: {{ $currentBusiness->nit }}</span>
                        @endif
                    </p>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="badge bg-primary-subtle text-primary px-3 py-2 rounded-pill fs-6">
                        <i class="bi bi-calendar3 me-1"></i> {{ now()->translatedFormat('d M, Y') }}
                    </span>
                    @can('create', App\Models\Sale::class)
                        <a href="{{ route('sales.create') }}" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm">
                            <i class="bi bi-cart-plus me-1"></i> Nueva Venta
                        </a>
                    @endcan
                </div>
            </div>
        </div>
    </div>

    {{-- Tarjetas KPI --}}
    @if(!empty($isAdmin))
        {{-- Tarjetas Financieras (Exclusivo Administrador) --}}
        <div class="col-sm-6 col-xl-3">
            <div class="card card-custom p-3 bg-white h-100 border-0 shadow-sm">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Ventas Hoy</span>
                        <h3 class="fw-bold mb-0 mt-1 text-success">
                            ${{ number_format($today_sales_total ?? 0, 0, ',', '.') }}
                        </h3>
                        <small class="text-muted">{{ $today_sales_count }} {{ $today_sales_count === 1 ? 'venta realizada' : 'ventas realizadas' }}</small>
                    </div>
                    <div class="bg-success-subtle text-success p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                        <i class="bi bi-cash-stack fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card card-custom p-3 bg-white h-100 border-0 shadow-sm">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Ventas del Mes</span>
                        <h3 class="fw-bold mb-0 mt-1 text-primary">
                            ${{ number_format($month_sales_total ?? 0, 0, ',', '.') }}
                        </h3>
                        <small class="text-muted">{{ $month_sales_count }} {{ $month_sales_count === 1 ? 'transacción' : 'transacciones' }}</small>
                    </div>
                    <div class="bg-primary-subtle text-primary p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                        <i class="bi bi-graph-up-arrow fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card card-custom p-3 bg-white h-100 border-0 shadow-sm">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Gastos / Compras del mes</span>
                        <h3 class="fw-bold mb-0 mt-1 text-danger">
                            ${{ number_format($month_expenses_total ?? 0, 0, ',', '.') }}
                        </h3>
                        <small class="text-muted">de los cuales ${{ number_format($month_expenses_pending ?? 0, 0, ',', '.') }} pendientes</small>
                    </div>
                    <div class="bg-danger-subtle text-danger p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                        <i class="bi bi-wallet2 fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card card-custom p-3 bg-white h-100 border-0 shadow-sm {{ ($estimated_net_profit ?? 0) < 0 ? 'border border-danger' : '' }}">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Utilidad neta estimada</span>
                        <h3 class="fw-bold mb-0 mt-1 {{ ($estimated_net_profit ?? 0) < 0 ? 'text-danger' : 'text-success' }}">
                            ${{ number_format($estimated_net_profit ?? 0, 0, ',', '.') }}
                        </h3>
                        <small class="text-muted">Margen operativo (estimada)</small>
                    </div>
                    <div class="{{ ($estimated_net_profit ?? 0) < 0 ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success' }} p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                        <i class="bi bi-calculator fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6">
            <div class="card card-custom p-3 bg-white h-100 border-0 shadow-sm">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Stock Crítico</span>
                        <h3 class="fw-bold mb-0 mt-1 {{ $low_stock_count > 0 ? 'text-danger' : 'text-secondary' }}">
                            {{ $low_stock_count }}
                        </h3>
                        <small class="text-muted">
                            @if($out_of_stock_count > 0)
                                <span class="text-danger fw-semibold">{{ $out_of_stock_count }} agotados</span>
                            @else
                                {{ $low_stock_count > 0 ? 'requieren reposición' : 'sin alertas de stock' }}
                            @endif
                        </small>
                    </div>
                    <div class="{{ $low_stock_count > 0 ? 'bg-danger-subtle text-danger' : 'bg-secondary-subtle text-secondary' }} p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                        <i class="bi bi-exclamation-triangle fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6">
            <div class="card card-custom p-3 bg-white h-100 border-0 shadow-sm">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Catálogo y Clientes</span>
                        <h3 class="fw-bold mb-0 mt-1 text-dark">
                            {{ $total_products }}
                        </h3>
                        <small class="text-muted">{{ $total_customers }} clientes activos</small>
                    </div>
                    <div class="bg-info-subtle text-info p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                        <i class="bi bi-boxes fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    @else
        {{-- Tarjetas Operativas (Vendedores / Colaboradores) --}}
        <div class="col-sm-6 col-xl-3">
            <div class="card card-custom p-3 bg-white h-100 border-0 shadow-sm">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Ventas Hoy</span>
                        <h3 class="fw-bold mb-0 mt-1 text-primary">
                            {{ $today_sales_count }}
                        </h3>
                        <small class="text-muted">{{ $today_sales_count === 1 ? 'venta realizada' : 'ventas realizadas' }}</small>
                    </div>
                    <div class="bg-primary-subtle text-primary p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                        <i class="bi bi-cart-check fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card card-custom p-3 bg-white h-100 border-0 shadow-sm">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Ventas del Mes</span>
                        <h3 class="fw-bold mb-0 mt-1 text-primary">
                            {{ $month_sales_count }}
                        </h3>
                        <small class="text-muted">{{ $month_sales_count === 1 ? 'transacción' : 'transacciones' }}</small>
                    </div>
                    <div class="bg-primary-subtle text-primary p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                        <i class="bi bi-graph-up fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card card-custom p-3 bg-white h-100 border-0 shadow-sm">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Stock Crítico</span>
                        <h3 class="fw-bold mb-0 mt-1 {{ $low_stock_count > 0 ? 'text-danger' : 'text-secondary' }}">
                            {{ $low_stock_count }}
                        </h3>
                        <small class="text-muted">
                            @if($out_of_stock_count > 0)
                                <span class="text-danger fw-semibold">{{ $out_of_stock_count }} agotados</span>
                            @else
                                {{ $low_stock_count > 0 ? 'requieren reposición' : 'sin alertas de stock' }}
                            @endif
                        </small>
                    </div>
                    <div class="{{ $low_stock_count > 0 ? 'bg-danger-subtle text-danger' : 'bg-secondary-subtle text-secondary' }} p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                        <i class="bi bi-exclamation-triangle fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card card-custom p-3 bg-white h-100 border-0 shadow-sm">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Catálogo y Clientes</span>
                        <h3 class="fw-bold mb-0 mt-1 text-dark">
                            {{ $total_products }}
                        </h3>
                        <small class="text-muted">{{ $total_customers }} clientes activos</small>
                    </div>
                    <div class="bg-info-subtle text-info p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                        <i class="bi bi-boxes fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Accesos Rápidos --}}
    <div class="col-12">
        <div class="card card-custom p-3 bg-white border-0 shadow-sm">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <span class="fw-semibold text-secondary small text-uppercase">Accesos directos:</span>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="{{ route('sales.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                        <i class="bi bi-receipt me-1"></i> Ver Ventas
                    </a>
                    <a href="{{ route('inventory.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                        <i class="bi bi-arrow-left-right me-1"></i> Control de Stock
                    </a>
                    <a href="{{ route('products.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                        <i class="bi bi-box me-1"></i> Productos
                    </a>
                    <a href="{{ route('customers.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                        <i class="bi bi-people me-1"></i> Clientes
                    </a>
                    @if(!empty($isAdmin))
                        <a href="{{ route('expenses.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                            <i class="bi bi-wallet2 me-1"></i> Gastos
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Columna Izquierda: Ventas Recientes --}}
    <div class="col-lg-7">
        <div class="card card-custom h-100 bg-white border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0">Últimas Ventas</h5>
                <a href="{{ route('sales.index') }}" class="text-primary small fw-semibold text-decoration-none">
                    Ver todas <i class="bi bi-chevron-right"></i>
                </a>
            </div>
            <div class="card-body px-4 pb-4">
                @if($recent_sales->isEmpty())
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-cart-x fs-1 d-block mb-2 text-secondary opacity-50"></i>
                        <p class="mb-2">Aún no se han registrado ventas en este emprendimiento.</p>
                        @can('create', App\Models\Sale::class)
                            <a href="{{ route('sales.create') }}" class="btn btn-sm btn-primary rounded-pill px-3">
                                <i class="bi bi-cart-plus me-1"></i> Registrar primera venta
                            </a>
                        @endcan
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="small text-muted fw-semibold">Factura</th>
                                    <th class="small text-muted fw-semibold">Cliente</th>
                                    @if(!empty($isAdmin))
                                        <th class="small text-muted fw-semibold text-end">Total</th>
                                    @endif
                                    <th class="small text-muted fw-semibold text-center">Método</th>
                                    <th class="small text-muted fw-semibold text-center">Estado</th>
                                    <th class="small text-muted fw-semibold text-end">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recent_sales as $sale)
                                    <tr>
                                        <td class="fw-bold text-dark">
                                            {{ $sale->invoice_number }}
                                            <div class="small text-muted fw-normal">
                                                {{ $sale->sale_date->format('d/m/Y H:i') }}
                                            </div>
                                        </td>
                                        <td>
                                            @if($sale->customer)
                                                <span class="fw-semibold text-dark">{{ $sale->customer->name }}</span>
                                            @else
                                                <span class="text-muted fst-italic">Consumidor Final</span>
                                            @endif
                                        </td>
                                        @if(!empty($isAdmin))
                                            <td class="text-end fw-bold text-dark">
                                                ${{ number_format($sale->total, 0, ',', '.') }}
                                            </td>
                                        @endif
                                        <td class="text-center">
                                            @php
                                                $methodLabels = [
                                                    'cash' => 'Efectivo',
                                                    'transfer' => 'Transf.',
                                                    'card' => 'Tarjeta',
                                                    'other' => 'Otro'
                                                ];
                                            @endphp
                                            <span class="badge bg-light text-dark border">
                                                {{ $methodLabels[$sale->payment_method] ?? $sale->payment_method }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            @if($sale->status === 'completed')
                                                <span class="badge bg-success-subtle text-success">Completada</span>
                                            @else
                                                <span class="badge bg-danger-subtle text-danger">Anulada</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('sales.show', $sale) }}" class="btn btn-sm btn-outline-primary rounded-circle" title="Ver detalle">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Columna Derecha: Alertas de Stock y Top Productos --}}
    <div class="col-lg-5 d-flex flex-column gap-4">
        {{-- Stock Crítico / Alertas --}}
        <div class="card card-custom bg-white border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0">Alertas de Stock</h5>
                <a href="{{ route('inventory.index') }}" class="text-primary small fw-semibold text-decoration-none">
                    Inventario <i class="bi bi-chevron-right"></i>
                </a>
            </div>
            <div class="card-body px-4 pb-4">
                @if($low_stock_products->isEmpty())
                    <div class="text-center py-3 text-success">
                        <i class="bi bi-check-circle-fill fs-3 d-block mb-1"></i>
                        <p class="mb-0 fw-semibold">¡Inventario en orden!</p>
                        <small class="text-muted">No tienes productos con existencias críticas.</small>
                    </div>
                @else
                    <div class="list-group list-group-flush">
                        @foreach($low_stock_products as $product)
                            <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold text-dark">{{ $product->name }}</div>
                                    <small class="text-muted">SKU: {{ $product->sku }} | Min: {{ $product->min_stock }}</small>
                                </div>
                                <div class="text-end">
                                    <span class="badge {{ $product->stock <= 0 ? 'bg-danger' : 'bg-warning text-dark' }} rounded-pill px-3 py-1">
                                        {{ $product->stock }} disp.
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Top 5 Productos Más Vendidos --}}
        <div class="card card-custom bg-white border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2">
                <h5 class="fw-bold mb-0">Más Vendidos</h5>
            </div>
            <div class="card-body px-4 pb-4">
                @if($top_products->isEmpty())
                    <div class="text-center py-3 text-muted">
                        <i class="bi bi-bar-chart fs-3 d-block mb-1 opacity-50"></i>
                        <p class="mb-0 small">Sin datos de ventas completadas.</p>
                    </div>
                @else
                    <div class="list-group list-group-flush">
                        @foreach($top_products as $idx => $item)
                            <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="badge bg-light text-dark border rounded-circle" style="width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center;">
                                        {{ $idx + 1 }}
                                    </span>
                                    <div>
                                        <div class="fw-semibold text-dark">{{ $item->name }}</div>
                                        <small class="text-muted">{{ $item->total_sold_quantity }} unid. vendidas</small>
                                    </div>
                                </div>
                                @if(!empty($isAdmin))
                                    <div class="text-end fw-bold text-dark">
                                        ${{ number_format($item->total_sold_revenue, 0, ',', '.') }}
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
