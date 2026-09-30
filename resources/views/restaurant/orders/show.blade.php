@extends('layouts.app')

@section('title', "Comanda {$order->order_number}")

@section('content')
<div class="container-fluid py-3" style="max-width: 1200px;">
    {{-- Header de la Comanda --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div class="d-flex align-items-center">
            <a href="{{ route('restaurant.tables.index') }}" class="btn btn-outline-secondary btn-sm me-3">
                <i class="bi bi-arrow-left"></i> Salón
            </a>
            <div>
                <h1 class="h3 fw-bold mb-0 text-dark">
                    Comanda <span class="text-primary">{{ $order->order_number }}</span>
                    @if($order->table)
                        <span class="badge bg-light text-dark border ms-2">
                            <i class="bi bi-aspect-ratio me-1"></i>{{ $order->table->name }}
                        </span>
                    @endif
                </h1>
                <p class="text-muted small mb-0">
                    Mesero: <strong>{{ $order->user->name ?? 'N/A' }}</strong> &bull;
                    Apertura: {{ $order->created_at->format('d/m/Y H:i') }} ({{ $order->created_at->diffForHumans() }})
                    @if($order->customer_name)
                        &bull; Cliente: <strong>{{ $order->customer_name }}</strong>
                    @endif
                </p>
            </div>
        </div>

        <div class="d-flex gap-2">
            {{-- Botón Enviar a Cocina / Imprimir Ticket --}}
            @php
                $hasPendingKitchen = $order->items->where('printed_to_kitchen', false)->where('status', '!=', 'cancelled')->count() > 0;
            @endphp
            <form action="{{ route('restaurant.orders.kitchen-ticket', $order) }}" method="POST" target="_blank" class="d-inline">
                @csrf
                <button type="submit" class="btn {{ $hasPendingKitchen ? 'btn-warning text-dark fw-bold animate-pulse' : 'btn-outline-secondary' }}">
                    <i class="bi bi-printer-fill me-1"></i>
                    {{ $hasPendingKitchen ? 'Enviar a Cocina / Imprimir (80mm)' : 'Reimprimir Comanda (80mm)' }}
                </button>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-3" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-4">
        {{-- Columna Izquierda: Ítems por Tandas --}}
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 pt-4 pb-2 px-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-list-check me-2 text-primary"></i>Platos y Productos Ordenados
                    </h5>
                    <span class="badge bg-light text-dark border">
                        Total Ítems: {{ $order->items->count() }}
                    </span>
                </div>

                <div class="card-body p-4">
                    @if($order->items->isEmpty())
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-cart-x fs-1 d-block mb-2"></i>
                            <p class="mb-0">No hay platos agregados a esta comanda aún.</p>
                        </div>
                    @else
                        @foreach($batches as $batchNumber => $items)
                            <div class="border rounded-3 mb-4 overflow-hidden">
                                <div class="bg-light px-3 py-2 border-bottom d-flex justify-content-between align-items-center">
                                    <span class="fw-bold text-dark">
                                        <i class="bi bi-clock-history me-1 text-primary"></i> Tanda #{{ $batchNumber }}
                                    </span>
                                    @php
                                        $allPrinted = $items->every(fn($i) => $i->printed_to_kitchen);
                                    @endphp
                                    <span class="badge {{ $allPrinted ? 'bg-success' : 'bg-warning text-dark' }}">
                                        {{ $allPrinted ? 'Enviado a Cocina' : 'Pendiente de Cocina' }}
                                    </span>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light small">
                                            <tr>
                                                <th class="ps-3">Cant.</th>
                                                <th>Plato / Producto</th>
                                                <th>Observaciones</th>
                                                <th class="text-end">P. Unit</th>
                                                <th class="text-end pe-3">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($items as $item)
                                                <tr>
                                                    <td class="ps-3 fw-bold text-primary" style="width: 70px;">
                                                        {{ (float) $item->quantity == (int) $item->quantity ? (int) $item->quantity : $item->quantity }}
                                                    </td>
                                                    <td>
                                                        <span class="fw-semibold text-dark">{{ $item->product->name }}</span>
                                                        @if($item->printed_to_kitchen)
                                                            <i class="bi bi-check2-all text-success ms-1" title="Impreso en cocina"></i>
                                                        @else
                                                            <span class="badge bg-warning text-dark ms-1" style="font-size: 0.7rem;">Sin imprimir</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($item->notes)
                                                            <span class="badge bg-light text-dark border fw-normal text-wrap text-start">
                                                                <i class="bi bi-chat-left-text me-1 text-muted"></i>{{ $item->notes }}
                                                            </span>
                                                        @else
                                                            <span class="text-muted small">—</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-end text-muted small">
                                                        ${{ number_format($item->unit_price, 2) }}
                                                    </td>
                                                    <td class="text-end pe-3 fw-bold">
                                                        ${{ number_format($item->subtotal, 2) }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>

            {{-- Formulario para Agregar Más Platos (Nueva Tanda) --}}
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 pt-4 pb-2 px-4">
                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-plus-circle-fill me-2 text-primary"></i>Agregar Platos a la Comanda
                    </h5>
                    <p class="text-muted small mb-0">Los platos agregados formarán parte de una nueva tanda para despacho.</p>
                </div>

                <div class="card-body p-4">
                    <form action="{{ route('restaurant.orders.items.store', $order) }}" method="POST" id="addItemsForm">
                        @csrf

                        <div id="new-items-container">
                            <div class="card bg-light border-0 mb-3 new-item-row p-3 rounded-3">
                                <div class="row g-2 align-items-center">
                                    <div class="col-12 col-md-5">
                                        <label class="form-label small fw-semibold">Plato / Producto <span class="text-danger">*</span></label>
                                        <select class="form-select form-select-sm" name="items[0][product_id]" required>
                                            <option value="" disabled selected>-- Seleccionar --</option>
                                            @foreach($products as $prod)
                                                <option value="{{ $prod->id }}">
                                                    {{ $prod->name }} (${{ number_format($prod->sale_price, 2) }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-6 col-md-2">
                                        <label class="form-label small fw-semibold">Cantidad <span class="text-danger">*</span></label>
                                        <input type="number" step="any" min="0.001" value="1" class="form-control form-select-sm" name="items[0][quantity]" required>
                                    </div>
                                    <div class="col-6 col-md-4">
                                        <label class="form-label small fw-semibold">Observación</label>
                                        <input type="text" class="form-control form-select-sm" name="items[0][notes]" placeholder="Ej: Sin cebolla, término 3/4">
                                    </div>
                                    <div class="col-12 col-md-1 text-end pt-md-4">
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-row-btn" disabled>
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center pt-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-add-more-row">
                                <i class="bi bi-plus-lg me-1"></i> Otra Línea
                            </button>
                            <button type="submit" class="btn btn-primary px-4 fw-bold">
                                <i class="bi bi-check-lg me-1"></i> Confirmar y Agregar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Columna Derecha: Resumen de Totales y Mesa --}}
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 sticky-top" style="top: 20px;">
                <div class="card-header bg-white border-0 pt-4 pb-2 px-4">
                    <h5 class="fw-bold mb-0">Resumen de Comanda</h5>
                </div>

                <div class="card-body px-4 pb-4">
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted">Subtotal:</span>
                        <span class="fw-semibold text-dark">${{ number_format($order->subtotal, 2) }}</span>
                    </div>

                    @if($order->delivery_fee > 0)
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span class="text-muted">Domicilio:</span>
                            <span class="fw-semibold text-dark">${{ number_format($order->delivery_fee, 2) }}</span>
                        </div>
                    @endif

                    <div class="d-flex justify-content-between py-3">
                        <span class="h5 fw-bold text-dark mb-0">Total:</span>
                        <span class="h4 fw-bold text-primary mb-0">${{ number_format($order->total, 2) }}</span>
                    </div>

                    <div class="bg-light rounded-3 p-3 mt-3 text-start small">
                        <div class="mb-2">
                            <span class="text-muted d-block">Estado de Comanda:</span>
                            <span class="badge bg-primary fs-6">{{ ucfirst($order->status) }}</span>
                        </div>
                        @if($order->notes)
                            <div class="mt-2 pt-2 border-top">
                                <span class="text-muted d-block fw-semibold">Notas:</span>
                                <span class="text-dark">{{ $order->notes }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<template id="more-row-template">
    <div class="card bg-light border-0 mb-3 new-item-row p-3 rounded-3">
        <div class="row g-2 align-items-center">
            <div class="col-12 col-md-5">
                <label class="form-label small fw-semibold">Plato / Producto <span class="text-danger">*</span></label>
                <select class="form-select form-select-sm" name="items[INDEX][product_id]" required>
                    <option value="" disabled selected>-- Seleccionar --</option>
                    @foreach($products as $prod)
                        <option value="{{ $prod->id }}">
                            {{ $prod->name }} (${{ number_format($prod->sale_price, 2) }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold">Cantidad <span class="text-danger">*</span></label>
                <input type="number" step="any" min="0.001" value="1" class="form-control form-select-sm" name="items[INDEX][quantity]" required>
            </div>
            <div class="col-6 col-md-4">
                <label class="form-label small fw-semibold">Observación</label>
                <input type="text" class="form-control form-select-sm" name="items[INDEX][notes]" placeholder="Ej: Sin cebolla, término 3/4">
            </div>
            <div class="col-12 col-md-1 text-end pt-md-4">
                <button type="button" class="btn btn-sm btn-outline-danger remove-row-btn">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>
    </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('new-items-container');
    const template = document.getElementById('more-row-template');
    const addBtn = document.getElementById('btn-add-more-row');
    let rowIndex = 1;

    if (addBtn && template && container) {
        addBtn.addEventListener('click', function () {
            const clone = template.content.cloneNode(true);
            const row = clone.querySelector('.new-item-row');
            row.innerHTML = row.innerHTML.replace(/INDEX/g, rowIndex);
            
            row.querySelector('.remove-row-btn').addEventListener('click', function () {
                row.remove();
            });

            container.appendChild(clone);
            rowIndex++;
        });
    }
});
</script>
@endsection
