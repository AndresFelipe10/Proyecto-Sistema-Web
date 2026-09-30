@extends('layouts.app')

@section('title', 'Historial de Comandas')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark">
                <i class="bi bi-receipt text-primary me-2"></i>Comandas
            </h1>
            <p class="text-muted small mb-0">Listado y seguimiento de pedidos de salón y cocina.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('restaurant.tables.index') }}" class="btn btn-outline-primary">
                <i class="bi bi-grid-3x3-gap-fill me-1"></i> Mapa de Mesas
            </a>
            <a href="{{ route('restaurant.orders.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i> Abrir Nueva Comanda
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            @if($orders->isEmpty())
                <div class="text-center py-5">
                    <i class="bi bi-receipt text-muted display-4 mb-3"></i>
                    <h5 class="fw-bold">No hay comandas registradas</h5>
                    <p class="text-muted">Abre una comanda desde una mesa del salón para comenzar.</p>
                    <a href="{{ route('restaurant.tables.index') }}" class="btn btn-primary mt-2">
                        <i class="bi bi-grid-3x3-gap-fill me-1"></i> Ir al Salón
                    </a>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Comanda</th>
                                <th>Mesa / Tipo</th>
                                <th>Cliente</th>
                                <th>Mesero</th>
                                <th>Estado</th>
                                <th>Total</th>
                                <th>Fecha y Hora</th>
                                <th class="text-end pe-4">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($orders as $order)
                                <tr>
                                    <td class="ps-4 fw-bold text-primary">
                                        {{ $order->order_number }}
                                    </td>
                                    <td>
                                        @if($order->table)
                                            <span class="badge bg-light text-dark border">
                                                <i class="bi bi-aspect-ratio me-1"></i>{{ $order->table->name }}
                                            </span>
                                        @else
                                            <span class="badge bg-secondary">
                                                {{ ucfirst($order->order_type) }}
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $order->customer_name ?? 'Consumidor Final' }}
                                    </td>
                                    <td>
                                        <small class="text-muted">{{ $order->user->name ?? 'N/A' }}</small>
                                    </td>
                                    <td>
                                        @php
                                            $badges = [
                                                'open' => 'bg-secondary',
                                                'in_kitchen' => 'bg-warning text-dark',
                                                'dispatched' => 'bg-info text-dark',
                                                'delivered' => 'bg-primary',
                                                'closed' => 'bg-success',
                                                'cancelled' => 'bg-danger',
                                            ];
                                            $labels = [
                                                'open' => 'Abierta',
                                                'in_kitchen' => 'En Cocina',
                                                'dispatched' => 'Despachada',
                                                'delivered' => 'Servida',
                                                'closed' => 'Cerrada / Pagada',
                                                'cancelled' => 'Cancelada',
                                            ];
                                        @endphp
                                        <span class="badge {{ $badges[$order->status] ?? 'bg-secondary' }}">
                                            {{ $labels[$order->status] ?? $order->status }}
                                        </span>
                                    </td>
                                    <td class="fw-bold">
                                        ${{ number_format($order->total, 2) }}
                                    </td>
                                    <td class="small text-muted">
                                        {{ $order->created_at->format('d/m/Y H:i') }}
                                    </td>
                                    <td class="text-end pe-4">
                                        <a href="{{ route('restaurant.orders.show', $order) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye me-1"></i> Gestionar
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($orders->hasPages())
                    <div class="p-3 border-top">
                        {{ $orders->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
@endsection
