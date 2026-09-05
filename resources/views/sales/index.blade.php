@extends('layouts.app')

@section('title', 'Ventas')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h3 class="fw-bold mb-1">Ventas</h3>
        <p class="text-muted mb-0">Historial de ventas y comprobantes</p>
    </div>
    @can('create', App\Models\Sale::class)
        <a href="{{ route('sales.create') }}" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm">
            <i class="bi bi-cart-plus me-1"></i> Nueva Venta
        </a>
    @endcan
</div>

{{-- Filtros --}}
<div class="card card-custom p-3 mb-4">
    <form method="GET" action="{{ route('sales.index') }}">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="search" class="form-label small fw-semibold text-muted">Buscar</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="search" name="search" class="form-control border-start-0"
                           placeholder="N° factura o cliente" value="{{ $search ?? '' }}">
                </div>
            </div>
            <div class="col-md-2">
                <label for="from" class="form-label small fw-semibold text-muted">Desde</label>
                <input type="date" id="from" name="from" class="form-control" value="{{ $from ?? '' }}">
            </div>
            <div class="col-md-2">
                <label for="to" class="form-label small fw-semibold text-muted">Hasta</label>
                <input type="date" id="to" name="to" class="form-control" value="{{ $to ?? '' }}">
            </div>
            <div class="col-md-2">
                <label for="payment_method" class="form-label small fw-semibold text-muted">Método</label>
                <select id="payment_method" name="payment_method" class="form-select">
                    <option value="">Todos</option>
                    <option value="cash" {{ ($selectedPaymentMethod ?? '') === 'cash' ? 'selected' : '' }}>Efectivo</option>
                    <option value="transfer" {{ ($selectedPaymentMethod ?? '') === 'transfer' ? 'selected' : '' }}>Transferencia</option>
                    <option value="card" {{ ($selectedPaymentMethod ?? '') === 'card' ? 'selected' : '' }}>Tarjeta</option>
                    <option value="other" {{ ($selectedPaymentMethod ?? '') === 'other' ? 'selected' : '' }}>Otro</option>
                </select>
            </div>
            <div class="col-md-1">
                <label for="status" class="form-label small fw-semibold text-muted">Estado</label>
                <select id="status" name="status" class="form-select">
                    <option value="">Todos</option>
                    <option value="completed" {{ ($selectedStatus ?? '') === 'completed' ? 'selected' : '' }}>Completada</option>
                    <option value="cancelled" {{ ($selectedStatus ?? '') === 'cancelled' ? 'selected' : '' }}>Anulada</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary rounded-pill px-3 fw-semibold flex-fill">
                    <i class="bi bi-funnel me-1"></i> Filtrar
                </button>
                <a href="{{ route('sales.index') }}" class="btn btn-outline-secondary rounded-pill px-3">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </div>
    </form>
</div>

{{-- Tabla --}}
<div class="card card-custom">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
                <tr>
                    <th class="ps-4">Factura</th>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Vendedor</th>
                    <th class="text-center">Método</th>
                    <th class="text-end">Total</th>
                    <th class="text-center">Estado</th>
                    <th class="text-center pe-4">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sales as $sale)
                    <tr>
                        <td class="ps-4">
                            <span class="font-monospace fw-semibold text-primary">{{ $sale->invoice_number }}</span>
                        </td>
                        <td>{{ $sale->sale_date->format('d/m/Y H:i') }}</td>
                        <td>{{ $sale->customer ? $sale->customer->name : 'Mostrador' }}</td>
                        <td>{{ $sale->user->name }}</td>
                        <td class="text-center">
                            @switch($sale->payment_method)
                                @case('cash')
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">
                                        <i class="bi bi-cash me-1"></i> Efectivo
                                    </span>
                                    @break
                                @case('transfer')
                                    <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill">
                                        <i class="bi bi-bank me-1"></i> Transferencia
                                    </span>
                                    @break
                                @case('card')
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">
                                        <i class="bi bi-credit-card me-1"></i> Tarjeta
                                    </span>
                                    @break
                                @default
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill">
                                        <i class="bi bi-three-dots me-1"></i> Otro
                                    </span>
                            @endswitch
                        </td>
                        <td class="text-end fw-bold">${{ number_format($sale->total, 0, ',', '.') }}</td>
                        <td class="text-center">
                            @if ($sale->status === 'completed')
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1">
                                    <i class="bi bi-check-circle-fill me-1"></i> Completada
                                </span>
                            @else
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-1">
                                    <i class="bi bi-x-circle-fill me-1"></i> Anulada
                                </span>
                            @endif
                        </td>
                        <td class="text-center pe-4">
                            <a href="{{ route('sales.show', $sale) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                <i class="bi bi-receipt me-1"></i> Ver
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">
                            <i class="bi bi-cart-x fs-1 d-block mb-2 opacity-50"></i>
                            No se encontraron ventas registradas.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($sales->hasPages())
    <div class="d-flex justify-content-center mt-4">
        {{ $sales->links() }}
    </div>
@endif
@endsection
