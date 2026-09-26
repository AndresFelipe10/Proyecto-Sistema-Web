@extends('layouts.app')

@section('title', 'Reporte de Ventas')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
    <div>
        <h3 class="fw-bold mb-1">Reporte de Ventas</h3>
        <p class="text-muted small mb-0">Desempeño comercial y análisis de transacciones en <strong>{{ $currentBusiness->name }}</strong>.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('reports.sales', array_merge(request()->all(), ['export' => 'csv'])) }}" class="btn btn-outline-success rounded-pill px-4 fw-semibold shadow-sm">
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
            <span class="text-muted small fw-semibold text-uppercase">Ingresos Totales</span>
            <h3 class="fw-bold mb-0 mt-1 text-success">${{ number_format($total_revenue, 0, ',', '.') }}</h3>
            <small class="text-muted">{{ $sales_count }} {{ $sales_count === 1 ? 'venta' : 'ventas' }} en el período</small>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card card-custom p-3 bg-white h-100 border-0 shadow-sm">
            <span class="text-muted small fw-semibold text-uppercase">Ticket Promedio</span>
            <h3 class="fw-bold mb-0 mt-1 text-primary">${{ number_format($average_ticket, 0, ',', '.') }}</h3>
            <small class="text-muted">Monto promedio por factura</small>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card card-custom p-3 bg-white h-100 border-0 shadow-sm">
            <span class="text-muted small fw-semibold text-uppercase">Descuentos Otorgados</span>
            <h3 class="fw-bold mb-0 mt-1 text-dark">${{ number_format($total_discounts, 0, ',', '.') }}</h3>
            <small class="text-muted">Total rebajas aplicadas</small>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card card-custom p-3 bg-white h-100 border-0 shadow-sm">
            <span class="text-muted small fw-semibold text-uppercase">Transacciones</span>
            <h3 class="fw-bold mb-0 mt-1 text-info">{{ $sales_count }}</h3>
            <small class="text-muted">Ventas completadas</small>
        </div>
    </div>
</div>

{{-- Filtros --}}
<div class="card card-custom p-3 bg-white mb-4 border-0 shadow-sm">
    <form method="GET" action="{{ route('reports.sales') }}" class="row g-2 align-items-center">
        <div class="col-md-3">
            <label for="date_from" class="form-label small text-muted mb-1">Desde</label>
            <input type="date" id="date_from" name="date_from" class="form-control" value="{{ $date_from }}">
        </div>
        <div class="col-md-3">
            <label for="date_to" class="form-label small text-muted mb-1">Hasta</label>
            <input type="date" id="date_to" name="date_to" class="form-control" value="{{ $date_to }}">
        </div>
        <div class="col-md-2">
            <label for="payment_method" class="form-label small text-muted mb-1">Método</label>
            <select id="payment_method" name="payment_method" class="form-select">
                <option value="">Todos</option>
                <option value="cash" {{ $selected_payment_method === 'cash' ? 'selected' : '' }}>Efectivo</option>
                <option value="transfer" {{ $selected_payment_method === 'transfer' ? 'selected' : '' }}>Transferencia</option>
                <option value="card" {{ $selected_payment_method === 'card' ? 'selected' : '' }}>Tarjeta</option>
                <option value="other" {{ $selected_payment_method === 'other' ? 'selected' : '' }}>Otro</option>
            </select>
        </div>
        <div class="col-md-2">
            <label for="customer_id" class="form-label small text-muted mb-1">Cliente</label>
            <select id="customer_id" name="customer_id" class="form-select">
                <option value="">Todos</option>
                @foreach($customers as $c)
                    <option value="{{ $c->id }}" {{ (string)$selected_customer_id === (string)$c->id ? 'selected' : '' }}>
                        {{ $c->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2 d-flex align-items-end gap-2 pt-4">
            <button type="submit" class="btn btn-primary rounded-pill flex-grow-1 fw-semibold">
                <i class="bi bi-funnel me-1"></i> Filtrar
            </button>
            @if(request()->has('date_from') || request()->has('payment_method') || request()->has('customer_id'))
                <a href="{{ route('reports.sales') }}" class="btn btn-light rounded-pill text-secondary" title="Limpiar"><i class="bi bi-x-lg"></i></a>
            @endif
        </div>
    </form>
</div>

{{-- Tabla de Ventas --}}
<div class="card card-custom p-0 bg-white border-0 shadow-sm overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">Factura</th>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Vendedor</th>
                    <th class="text-center">Método</th>
                    <th class="text-end">Subtotal</th>
                    <th class="text-end">Descuento</th>
                    <th class="text-end pe-4">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sales as $sale)
                    <tr>
                        <td class="ps-4 fw-bold">
                            <a href="{{ route('sales.show', $sale) }}" class="text-decoration-none text-primary">
                                {{ $sale->invoice_number }}
                            </a>
                        </td>
                        <td class="text-muted small">
                            {{ $sale->sale_date->format('d/m/Y H:i') }}
                        </td>
                        <td>
                            @if($sale->customer)
                                <span class="fw-semibold text-dark">{{ $sale->customer->name }}</span>
                            @else
                                <span class="text-muted fst-italic">Consumidor Final</span>
                            @endif
                        </td>
                        <td class="text-muted small">{{ $sale->user ? $sale->user->name : 'N/A' }}</td>
                        <td class="text-center">
                            @php
                                $methods = ['cash' => 'Efectivo', 'transfer' => 'Transf.', 'card' => 'Tarjeta', 'other' => 'Otro'];
                            @endphp
                            <span class="badge bg-light text-dark border">
                                {{ $methods[$sale->payment_method] ?? $sale->payment_method }}
                            </span>
                        </td>
                        <td class="text-end text-muted">${{ number_format($sale->subtotal, 0, ',', '.') }}</td>
                        <td class="text-end text-muted">
                            @if($sale->discount > 0)
                                {{ number_format($sale->discount_percentage, 1) }}% (${{ number_format($sale->discount, 0, ',', '.') }})
                            @else
                                $0
                            @endif
                        </td>
                        <td class="text-end pe-4 fw-bold text-dark">${{ number_format($sale->total, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-cart-x fs-1 d-block mb-2 text-secondary opacity-50"></i>
                            No se encontraron ventas para el período o filtros seleccionados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
