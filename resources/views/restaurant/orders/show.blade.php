@extends('layouts.app')

@section('title', "Comanda {$order->order_number}")

@section('content')
@php
    $groupedProducts = $groupedProducts ?? $products->groupBy(fn($p) => $p->category?->name ?? 'General / Sin Categoría');
    $waiterName = ($order->order_type === 'table' && !empty($order->customer_name))
        ? $order->customer_name
        : ($order->user->name ?? 'Usuario');
    $clientName = $order->customer?->name
        ?? ($order->order_type !== 'table' && !empty($order->customer_name) ? $order->customer_name : 'Consumidor Final');
@endphp
<div class="container-fluid py-3" style="max-width: 1200px;">
    {{-- Header de la Comanda --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div class="d-flex align-items-center">
            @if($order->order_type === 'table')
                <a href="{{ route('restaurant.tables.index') }}" class="btn btn-outline-secondary btn-sm me-3">
                    <i class="bi bi-arrow-left"></i> Salón
                </a>
            @else
                <a href="{{ route('restaurant.orders.deliveries') }}" class="btn btn-outline-secondary btn-sm me-3">
                    <i class="bi bi-arrow-left"></i> Domicilios
                </a>
            @endif
            <div>
                <h1 class="h3 fw-bold mb-0 text-dark">
                    Comanda <span class="text-primary">{{ $order->order_number }}</span>
                    @if($order->table)
                        <span class="badge bg-light text-dark border ms-2">
                            <i class="bi bi-aspect-ratio me-1"></i>{{ $order->table->name }}
                        </span>
                    @elseif($order->order_type === 'delivery')
                        <span class="badge bg-info-subtle text-info-emphasis border ms-2">
                            <i class="bi bi-bicycle me-1"></i>Domicilio
                        </span>
                    @elseif($order->order_type === 'takeout')
                        <span class="badge bg-secondary-subtle text-secondary-emphasis border ms-2">
                            <i class="bi bi-bag me-1"></i>Para Llevar
                        </span>
                    @endif
                </h1>
                <p class="text-muted small mb-0">
                    Mesero / Atendido por: <strong>{{ $waiterName }}</strong> &bull;
                    Apertura: {{ $order->created_at->format('d/m/Y H:i') }} ({{ $order->created_at->diffForHumans() }})
                    &bull; Cliente: <strong>{{ $clientName }}</strong>
                    @if($order->guest_count)
                        &bull; Personas: <strong>{{ $order->guest_count }}</strong>
                    @endif
                </p>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2">
            {{-- Acceso a Configuración de Impresión de Comandas --}}
            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#kitchenConfigModal" title="Configuración de Comandas">
                <i class="bi bi-gear-fill me-1"></i> Configuración
            </button>

            {{-- Botón Enviar a Cocina / Imprimir Ticket --}}
            @php
                $hasPendingKitchen = $order->items->where('printed_to_kitchen', false)->where('status', '!=', 'cancelled')->count() > 0;
                $activeItems = $order->items->where('status', '!=', 'cancelled');
            @endphp

            @if(!in_array($order->status, ['closed', 'cancelled']))
                <form action="{{ route('restaurant.orders.kitchen-ticket', $order) }}" method="POST" target="_blank" class="d-inline">
                    @csrf
                    <button type="submit" class="btn {{ $hasPendingKitchen ? 'btn-warning text-dark fw-bold animate-pulse' : 'btn-outline-secondary' }}">
                        <i class="bi bi-printer-fill me-1"></i>
                        {{ $hasPendingKitchen ? 'Enviar a Cocina (80mm)' : 'Reimprimir Cocina (80mm)' }}
                    </button>
                </form>
            @endif

            {{-- Botón Pre-cuenta (Mesas) --}}
            @if($order->order_type === 'table' && !in_array($order->status, ['closed', 'cancelled']))
                <a href="{{ route('restaurant.orders.prebill', $order) }}" target="_blank" class="btn btn-outline-warning text-dark fw-bold">
                    <i class="bi bi-file-earmark-text me-1"></i> Pre-cuenta (80mm)
                </a>
            @endif

            {{-- Botón Cancelar Comanda Vacía --}}
            @if($order->status === 'open' && $order->items->isEmpty())
                <form action="{{ route('restaurant.orders.cancel-empty', $order) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Confirmas que deseas cancelar esta comanda vacía y liberar la mesa?');">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger fw-semibold">
                        <i class="bi bi-x-circle me-1"></i> Cancelar Comanda Vacía
                    </button>
                </form>
            @endif

            {{-- Botón Cobrar / Facturar --}}
            @if(!in_array($order->status, ['closed', 'cancelled']))
                <button type="button" class="btn btn-success fw-bold text-white shadow-sm" data-bs-toggle="modal" data-bs-target="#settlementModal" {{ $activeItems->isEmpty() ? 'disabled' : '' }}>
                    <i class="bi bi-cash-coin me-1"></i> Cobrar / Facturar
                </button>
            @elseif($order->status === 'closed' && $order->sale_id)
                <a href="{{ route('sales.show', $order->sale_id) }}" class="btn btn-outline-primary fw-bold">
                    <i class="bi bi-receipt me-1"></i> Factura de Venta
                </a>
                <a href="{{ route('sales.print.receipt', $order->sale_id) }}" target="_blank" class="btn btn-outline-secondary">
                    <i class="bi bi-printer me-1"></i> Ticket Fiscal (80mm)
                </a>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-3" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show rounded-3" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Error al procesar:</strong>
            <ul class="mb-0 mt-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
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
                            <i class="bi bi-cart-x fs-1 d-block mb-2 text-secondary"></i>
                            <p class="mb-2 fw-semibold">No hay platos agregados a esta comanda aún.</p>
                            @if($order->status === 'open')
                                <p class="small text-muted mb-3">Si esta comanda fue abierta por error, puedes anularla y liberar la mesa inmediatamente.</p>
                                <form action="{{ route('restaurant.orders.cancel-empty', $order) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Confirmas que deseas cancelar esta comanda vacía y liberar la mesa?');">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-danger btn-sm px-3">
                                        <i class="bi bi-x-circle me-1"></i> Cancelar Comanda Vacía
                                    </button>
                                </form>
                            @endif
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

            {{-- Formulario para Agregar Más Platos (Solo si comanda está activa) --}}
            @if(!in_array($order->status, ['closed', 'cancelled']))
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white border-0 pt-4 pb-2 px-4">
                        <h5 class="fw-bold mb-0">
                            <i class="bi bi-plus-circle-fill me-2 text-primary"></i>Agregar Platos a la Comanda
                        </h5>
                        <p class="text-muted small mb-0">Los platos agregados formarán parte de una nueva tanda para despacho.</p>
                    </div>

                    <div class="card-body p-4">
                        @php
                            $groupedProducts = $products->groupBy(fn($p) => $p->category?->name ?? 'General / Sin Categoría');
                        @endphp
                        <form action="{{ route('restaurant.orders.items.store', $order) }}" method="POST" id="addItemsForm">
                            @csrf

                            <div id="new-items-container">
                                <div class="card bg-light border-0 mb-3 new-item-row p-3 rounded-3">
                                    <div class="row g-2 align-items-center">
                                        <div class="col-12 col-md-5">
                                            <label class="form-label small fw-semibold">Plato / Producto <span class="text-danger">*</span></label>
                                            <div class="position-relative mb-1">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search text-muted"></i></span>
                                                    <input type="text" class="form-control form-control-sm border-start-0 product-search-input" placeholder="Escribe para buscar plato o bebida..." autocomplete="off">
                                                </div>
                                                <div class="product-dropdown-list list-group position-absolute w-100 shadow-lg border rounded-3 overflow-auto d-none" style="max-height: 220px; z-index: 1050; top: 100%; left: 0; background: #fff;"></div>
                                            </div>
                                            <select class="form-select form-select-sm product-select" name="items[0][product_id]" required>
                                                <option value="" disabled selected>-- O selecciona del menú agrupado --</option>
                                                @foreach($groupedProducts as $categoryName => $catProducts)
                                                    <optgroup label="{{ $categoryName }}">
                                                        @foreach($catProducts as $prod)
                                                            <option value="{{ $prod->id }}" data-category="{{ $categoryName }}" data-price="{{ $prod->sale_price }}">
                                                                {{ $prod->name }} (${{ number_format($prod->sale_price, 2) }})
                                                            </option>
                                                        @endforeach
                                                    </optgroup>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-6 col-md-2">
                                            <label class="form-label small fw-semibold">Cantidad <span class="text-danger">*</span></label>
                                            <div class="input-group input-group-sm" style="max-width: 130px;">
                                                <button type="button" class="btn btn-outline-secondary btn-qty-minus fw-bold px-2">−</button>
                                                <input type="number" name="items[0][quantity]" class="form-control text-center input-qty input-stepper" value="1" min="1" max="999" required>
                                                <button type="button" class="btn btn-outline-secondary btn-qty-plus fw-bold px-2">+</button>
                                            </div>
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

                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pt-3 border-top">
                                <button type="button" class="btn btn-outline-primary fw-semibold px-3 py-2 d-inline-flex align-items-center" id="btn-add-more-row" style="min-height: 44px;">
                                    <i class="bi bi-plus-circle fs-5 me-2"></i> + Agregar otro plato
                                </button>
                                <button type="submit" class="btn btn-primary px-4 py-2 fw-bold d-inline-flex align-items-center shadow-sm" style="min-height: 44px;">
                                    <i class="bi bi-send-check fs-5 me-2"></i> Confirmar y Enviar a Cocina
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
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
                            <span class="text-muted">Flete Domicilio:</span>
                            <span class="fw-semibold text-dark">${{ number_format($order->delivery_fee, 2) }}</span>
                        </div>
                    @endif

                    <div class="d-flex justify-content-between py-3">
                        <span class="h5 fw-bold text-dark mb-0">Total:</span>
                        <span class="h4 fw-bold text-primary mb-0">${{ number_format($order->total, 2) }}</span>
                    </div>

                    <div class="bg-light rounded-3 p-3 mt-1 text-start small">
                        <div class="mb-2">
                            <span class="text-muted d-block">Estado de Comanda:</span>
                            @switch($order->status)
                                @case('open')
                                    <span class="badge bg-info text-dark fs-6">Abierta</span>
                                    @break
                                @case('in_kitchen')
                                    <span class="badge bg-warning text-dark fs-6">En Cocina</span>
                                    @break
                                @case('dispatched')
                                    <span class="badge bg-primary text-white fs-6">En Despacho</span>
                                    @break
                                @case('delivered')
                                    <span class="badge bg-secondary text-white fs-6">Entregado</span>
                                    @break
                                @case('billed')
                                    <span class="badge bg-warning text-dark fs-6"><i class="bi bi-clock-history me-1"></i>En Cobro / Pre-cuenta</span>
                                    @break
                                @case('closed')
                                    <span class="badge bg-success text-white fs-6"><i class="bi bi-check2-circle me-1"></i>Cerrada / Facturada</span>
                                    @break
                                @case('cancelled')
                                    <span class="badge bg-danger text-white fs-6">Anulada</span>
                                    @break
                                @default
                                    <span class="badge bg-secondary fs-6">{{ ucfirst($order->status) }}</span>
                            @endswitch
                        </div>
                        @if($order->notes)
                            <div class="mt-2 pt-2 border-top">
                                <span class="text-muted d-block fw-semibold">Notas:</span>
                                <span class="text-dark">{{ $order->notes }}</span>
                            </div>
                        @endif
                    </div>

                    @if(!in_array($order->status, ['closed', 'cancelled']))
                        <button type="button" class="btn btn-success btn-lg w-100 fw-bold text-white shadow-sm mt-3" data-bs-toggle="modal" data-bs-target="#settlementModal" {{ $activeItems->isEmpty() ? 'disabled' : '' }}>
                            <i class="bi bi-cash-coin me-1"></i> Cobrar / Facturar
                        </button>
                    @elseif($order->status === 'closed' && $order->sale_id)
                        <div class="mt-3">
                            <a href="{{ route('sales.show', $order->sale_id) }}" class="btn btn-outline-primary w-100 fw-bold mb-2">
                                <i class="bi bi-receipt me-1"></i> Ver Factura
                            </a>
                            <a href="{{ route('sales.print.receipt', $order->sale_id) }}" target="_blank" class="btn btn-outline-secondary w-100">
                                <i class="bi bi-printer me-1"></i> Imprimir Ticket Fiscal (80mm)
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- MODAL DE LIQUIDACIÓN Y FACTURACIÓN INTEGRADA --}}
@if(!in_array($order->status, ['closed', 'cancelled']))
<div class="modal fade" id="settlementModal" tabindex="-1" aria-labelledby="settlementModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold" id="settlementModalLabel">
                    <i class="bi bi-cash-coin me-2"></i>Facturación y Cobro — Comanda {{ $order->order_number }}
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form action="{{ route('sales.store') }}" method="POST" id="settlementForm">
                @csrf
                <input type="hidden" name="sale_date" value="{{ now()->format('Y-m-d\TH:i:s') }}">
                <input type="hidden" name="restaurant_order_id" value="{{ $order->id }}">
                <input type="hidden" name="order_type" value="{{ $order->order_type }}">
                <input type="hidden" name="delivery_fee" id="settlement_delivery_fee" value="{{ $order->delivery_fee }}">

                {{-- Productos de la comanda agrupados para facturación --}}
                @php
                    $groupedItems = [];
                    foreach ($activeItems as $item) {
                        $pid = $item->product_id;
                        if (!isset($groupedItems[$pid])) {
                            $groupedItems[$pid] = [
                                'product_id' => $pid,
                                'product_name' => $item->product->name ?? 'Plato',
                                'quantity' => 0,
                                'unit_price' => (float)$item->unit_price,
                            ];
                        }
                        $groupedItems[$pid]['quantity'] += (float)$item->quantity;
                    }
                @endphp
                @php $itemIdx = 0; @endphp
                @foreach($groupedItems as $g)
                    <input type="hidden" name="items[{{ $itemIdx }}][product_id]" value="{{ $g['product_id'] }}">
                    <input type="hidden" name="items[{{ $itemIdx }}][quantity]" value="{{ $g['quantity'] }}">
                    <input type="hidden" name="items[{{ $itemIdx }}][unit_price]" value="{{ $g['unit_price'] }}">
                    @php $itemIdx++; @endphp
                @endforeach

                <div class="modal-body p-4">
                    {{-- Datos del Comprador y Descuento --}}
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-7">
                            <label class="form-label small fw-semibold text-dark">Cliente / Comprador:</label>
                            <select name="customer_id" class="form-select form-select-sm">
                                <option value="">Consumidor Final DIAN (222222222222)</option>
                                @foreach($customers as $c)
                                    <option value="{{ $c->id }}" {{ (old('customer_id', $order->customer_id) == $c->id) ? 'selected' : '' }}>
                                        {{ $c->name }} (Doc: {{ $c->document ?? 'N/A' }})
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Por defecto asignado a Consumidor Final</small>
                        </div>
                        <div class="col-12 col-md-5">
                            <label class="form-label small fw-semibold text-dark">Porcentaje de Descuento:</label>
                            <div class="input-group input-group-sm">
                                <input type="number" step="0.5" min="0" max="100" name="discount_percentage" id="settlement_discount_pct" class="form-control text-end fw-bold" value="0">
                                <span class="input-group-text bg-light">%</span>
                            </div>
                            <small class="text-muted">Descuento aplicado sobre el subtotal</small>
                        </div>
                    </div>

                    {{-- Opciones Fiscales y Propinas (Servicio e INC) --}}
                    <div class="card border p-3 mb-3 rounded-3 bg-white">
                        <div class="row g-3">
                            {{-- Servicio Voluntario / Propina --}}
                            <div class="col-12 col-md-6 border-end-md">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="switch_service_fee" role="switch">
                                    <label class="form-check-label fw-semibold small text-dark" for="switch_service_fee">
                                        <i class="bi bi-heart text-danger me-1"></i> Incluir Servicio / Propina (10%)
                                    </label>
                                </div>
                                <div id="service_fee_container" class="d-none">
                                    <div class="input-group input-group-sm mb-1">
                                        <span class="input-group-text bg-light small">Tarifa:</span>
                                        <input type="number" step="0.5" min="0" max="100" id="service_fee_pct" class="form-control text-end fw-bold" value="10" style="max-width: 75px;">
                                        <span class="input-group-text bg-light">%</span>
                                        <span class="input-group-text bg-light">$</span>
                                        <input type="number" step="0.01" min="0" name="service_fee" id="settlement_service_fee" class="form-control text-end fw-bold" value="0.00">
                                    </div>
                                    <small class="text-muted d-block" style="font-size: 0.72rem;">Sugerido 10% voluntario sobre el consumo</small>
                                </div>
                            </div>

                            {{-- Impuesto Nacional al Consumo (INC) --}}
                            <div class="col-12 col-md-6">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="switch_tax_inc" role="switch">
                                    <label class="form-check-label fw-semibold small text-dark" for="switch_tax_inc">
                                        <i class="bi bi-receipt text-primary me-1"></i> Aplicar Impuesto al Consumo (INC 8%)
                                    </label>
                                </div>
                                <div id="tax_inc_container" class="d-none">
                                    <div class="input-group input-group-sm mb-1">
                                        <span class="input-group-text bg-light small">Tarifa:</span>
                                        <input type="text" class="form-control bg-light text-center fw-bold" value="8%" readonly style="max-width: 75px;">
                                        <span class="input-group-text bg-light">$</span>
                                        <input type="number" step="0.01" min="0" name="tax_inc" id="settlement_tax_inc" class="form-control text-end fw-bold" value="0.00" readonly>
                                    </div>
                                    <small class="text-muted d-block" style="font-size: 0.72rem;">8% sobre la base gravable de alimentos y bebidas</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Resumen de Cálculo Autoritativo --}}
                    <div class="card bg-light border-0 p-3 mb-3 rounded-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted">Subtotal Platos ({{ count($groupedItems) }} ref):</span>
                            <span class="fw-semibold text-dark" id="display_subtotal">${{ number_format($order->subtotal, 2) }}</span>
                        </div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted">Descuento (<span id="display_discount_pct_label">0%</span>):</span>
                            <span class="fw-semibold text-danger" id="display_discount">-$0.00</span>
                        </div>
                        @if($order->order_type === 'delivery')
                            <div class="d-flex justify-content-between small mb-1">
                                <span class="text-muted">Flete de Domicilio:</span>
                                <span class="fw-semibold text-dark" id="display_delivery_fee">+${{ number_format($order->delivery_fee, 2) }}</span>
                            </div>
                        @endif
                        <div class="d-flex justify-content-between small mb-1" id="row_service_fee" style="display: none !important;">
                            <span class="text-muted">Servicio / Propina (<span id="display_service_pct_label">10%</span>):</span>
                            <span class="fw-semibold text-dark" id="display_service_fee">+$0.00</span>
                        </div>
                        <div class="d-flex justify-content-between small mb-1" id="row_tax_inc" style="display: none !important;">
                            <span class="text-muted">Impuesto al Consumo (INC 8%):</span>
                            <span class="fw-semibold text-dark" id="display_tax_inc">+$0.00</span>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold fs-6 text-dark">Total a Liquidar:</span>
                            <span class="fw-bold fs-4 text-primary" id="display_total">${{ number_format($order->total, 2) }}</span>
                        </div>
                    </div>

                    {{-- Pasarela de Pagos Mixtos --}}
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label small fw-bold text-dark mb-0">
                                <i class="bi bi-wallet2 text-primary me-1"></i>Métodos de Pago
                            </label>
                            <button type="button" class="btn btn-xs btn-outline-primary py-1 px-2 rounded-pill" id="btnAddPaymentLine" style="font-size: 0.75rem;">
                                <i class="bi bi-plus-circle me-1"></i>+ Agregar otro medio
                            </button>
                        </div>

                        {{-- Atajos rápidos de efectivo --}}
                        <div class="d-flex flex-wrap gap-1 mb-2 align-items-center" id="quickCashButtons">
                            <span class="small text-muted me-1" style="font-size: 0.75rem;">Atajos Efectivo:</span>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 quick-cash-btn" data-type="exact" style="font-size: 0.75rem;">Exacto</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 quick-cash-btn" data-amt="20000" style="font-size: 0.75rem;">$20.000</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 quick-cash-btn" data-amt="50000" style="font-size: 0.75rem;">$50.000</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 quick-cash-btn" data-amt="100000" style="font-size: 0.75rem;">$100.000</button>
                        </div>

                        <div id="paymentsContainer">
                            {{-- Línea 1 por defecto --}}
                            <div class="payment-line card border p-2 mb-2 rounded-3 bg-white" data-index="0">
                                <div class="row g-2 align-items-center">
                                    <div class="col-12 col-md-3">
                                        <select name="payments[0][method]" class="form-select form-select-sm payment-method-select" required>
                                            <option value="cash" selected>Efectivo</option>
                                            <option value="transfer">Transferencia / Nequi</option>
                                            <option value="card">Tarjeta</option>
                                            <option value="other">Otro</option>
                                        </select>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">$</span>
                                            <input type="number" step="0.01" min="0.01" name="payments[0][amount]" class="form-control text-end payment-amount-input fw-bold" value="{{ $order->total }}" required>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3 cash-fields-col">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text" title="Efectivo Recibido"><i class="bi bi-box-arrow-in-down"></i></span>
                                            <input type="number" step="0.01" min="0" name="payments[0][cash_received]" class="form-control text-end payment-cash-received-input" placeholder="Recibido" value="{{ $order->total }}">
                                        </div>
                                    </div>
                                    <div class="col-10 col-md-2 text-end text-md-center change-display-col">
                                        <span class="badge bg-light text-dark border small payment-change-display">Vuelto: $0.00</span>
                                        <input type="hidden" name="payments[0][change_given]" class="payment-change-given-input" value="0.00">
                                    </div>
                                    <div class="col-2 col-md-1 text-end">
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-payment-btn" disabled>
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="row g-2 mt-1 reference-row" style="display: none;">
                                    <div class="col-12">
                                        <input type="text" name="payments[0][reference]" class="form-control form-control-sm payment-reference-input" placeholder="N° Comprobante / Referencia (opcional)" maxlength="60">
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Validación de diferencia de balance --}}
                        <div id="paymentBalanceAlert" class="alert alert-warning py-2 px-3 small d-none rounded-3 mt-2">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>
                            <span id="paymentBalanceMessage">La suma de los pagos no coincide con el total.</span>
                        </div>
                    </div>

                    {{-- Notas Opcionales --}}
                    <div>
                        <label class="form-label small fw-semibold text-muted">Observaciones de Facturación (Opcional):</label>
                        <input type="text" name="notes" class="form-control form-control-sm" placeholder="Ej: Mesa VIP, cliente frecuente" maxlength="255">
                    </div>
                </div>

                <div class="modal-footer bg-light py-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success fw-bold px-4" id="btnSubmitSettlement">
                        <i class="bi bi-check2-circle me-1"></i> Confirmar Pago y Generar Factura
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@if(!in_array($order->status, ['closed', 'cancelled']))
<template id="more-row-template">
    <div class="card bg-light border-0 mb-3 new-item-row p-3 rounded-3">
        <div class="row g-2 align-items-center">
            <div class="col-12 col-md-5">
                <label class="form-label small fw-semibold">Plato / Producto <span class="text-danger">*</span></label>
                <div class="position-relative mb-1">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" class="form-control form-control-sm border-start-0 product-search-input" placeholder="Escribe para buscar plato o bebida..." autocomplete="off">
                    </div>
                    <div class="product-dropdown-list list-group position-absolute w-100 shadow-lg border rounded-3 overflow-auto d-none" style="max-height: 220px; z-index: 1050; top: 100%; left: 0; background: #fff;"></div>
                </div>
                <select class="form-select form-select-sm product-select" name="items[INDEX][product_id]" required>
                    <option value="" disabled selected>-- O selecciona del menú agrupado --</option>
                    @foreach($groupedProducts as $categoryName => $catProducts)
                        <optgroup label="{{ $categoryName }}">
                            @foreach($catProducts as $prod)
                                <option value="{{ $prod->id }}" data-category="{{ $categoryName }}" data-price="{{ $prod->sale_price }}">
                                    {{ $prod->name }} (${{ number_format($prod->sale_price, 2) }})
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold">Cantidad <span class="text-danger">*</span></label>
                <div class="input-group input-group-sm" style="max-width: 130px;">
                    <button type="button" class="btn btn-outline-secondary btn-qty-minus fw-bold px-2">−</button>
                    <input type="number" name="items[INDEX][quantity]" class="form-control text-center input-qty input-stepper" value="1" min="1" max="999" required>
                    <button type="button" class="btn btn-outline-secondary btn-qty-plus fw-bold px-2">+</button>
                </div>
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
@endif

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Manejo de botones de cantidad (+ / -)
    function attachQuantityControls(row) {
        const btnMinus = row.querySelector('.btn-qty-minus');
        const btnPlus = row.querySelector('.btn-qty-plus');
        const qtyInput = row.querySelector('.input-qty');
        if (!btnMinus || !btnPlus || !qtyInput) return;

        btnMinus.addEventListener('click', function () {
            let val = parseInt(qtyInput.value) || 1;
            if (val > 1) {
                qtyInput.value = val - 1;
                qtyInput.dispatchEvent(new Event('input', { bubbles: true }));
                qtyInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });

        btnPlus.addEventListener('click', function () {
            let val = parseInt(qtyInput.value) || 1;
            qtyInput.value = val + 1;
            qtyInput.dispatchEvent(new Event('input', { bubbles: true }));
            qtyInput.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    // Manejo de filtrado rápido y combobox instantáneo de platos
    function attachProductSearch(row) {
        const searchInput = row.querySelector('.product-search-input');
        const select = row.querySelector('.product-select');
        const dropdown = row.querySelector('.product-dropdown-list');
        if (!searchInput || !select || !dropdown) return;

        // Normalización insensible a mayúsculas y acentos
        function normalizeStr(str) {
            return (str || '')
                .normalize("NFD")
                .replace(/[\u0300-\u036f]/g, "")
                .toLowerCase();
        }

        // Extraer lista de opciones
        const productsList = [];
        select.querySelectorAll('option').forEach(opt => {
            if (opt.value) {
                const rawText = opt.textContent.trim();
                const category = opt.getAttribute('data-category') || opt.closest('optgroup')?.label || 'General';
                const price = opt.getAttribute('data-price') || '';
                const cleanName = rawText.replace(/\s*\(\$[\d,\.]+\)\s*$/, '').trim();
                productsList.push({
                    id: opt.value,
                    fullName: rawText,
                    name: cleanName,
                    category: category,
                    price: price
                });
            }
        });

        let highlightedIndex = -1;

        function updateHighlight(items) {
            items.forEach((it, idx) => {
                if (idx === highlightedIndex) {
                    it.classList.add('active', 'bg-primary-subtle');
                    it.scrollIntoView({ block: 'nearest' });
                } else {
                    it.classList.remove('active', 'bg-primary-subtle');
                }
            });
        }

        function renderMatches(q) {
            dropdown.innerHTML = '';
            highlightedIndex = -1;
            if (!q) {
                dropdown.classList.add('d-none');
                return;
            }

            const normalizedQ = normalizeStr(q);
            const matches = productsList.filter(p => 
                normalizeStr(p.name).includes(normalizedQ) || 
                normalizeStr(p.category).includes(normalizedQ)
            );

            if (matches.length === 0) {
                const emptyItem = document.createElement('div');
                emptyItem.className = 'list-group-item text-muted small py-3 px-3 text-center';
                emptyItem.textContent = 'No se encontraron platos coincidentes';
                dropdown.appendChild(emptyItem);
                dropdown.classList.remove('d-none');
                return;
            }

            matches.forEach((p, idx) => {
                const itemBtn = document.createElement('button');
                itemBtn.type = 'button';
                itemBtn.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center text-start py-2 px-3 border-0 border-bottom product-result-item';
                itemBtn.style.minHeight = '44px';
                itemBtn.dataset.index = idx;

                const leftDiv = document.createElement('div');
                leftDiv.className = 'pe-2';

                const nameSpan = document.createElement('span');
                nameSpan.className = 'fw-semibold text-dark d-block';
                nameSpan.textContent = p.name;
                leftDiv.appendChild(nameSpan);

                if (p.category) {
                    const catBadge = document.createElement('span');
                    catBadge.className = 'badge bg-light text-secondary border';
                    catBadge.style.fontSize = '0.72rem';
                    catBadge.textContent = p.category;
                    leftDiv.appendChild(catBadge);
                }

                const rightDiv = document.createElement('div');
                rightDiv.className = 'text-end fw-bold text-primary small';
                rightDiv.textContent = p.price ? '$' + Number(p.price).toLocaleString('es-CO', {minimumFractionDigits: 2}) : '';

                itemBtn.appendChild(leftDiv);
                itemBtn.appendChild(rightDiv);

                function selectItem() {
                    select.value = p.id;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    searchInput.value = p.fullName;
                    dropdown.classList.add('d-none');
                    highlightedIndex = -1;

                    const qtyInput = row.querySelector('.input-qty');
                    const notesInput = row.querySelector('input[name*="[notes]"]');
                    if (qtyInput) {
                        qtyInput.focus();
                        try {
                            qtyInput.select();
                        } catch (err) {}
                    } else if (notesInput) {
                        notesInput.focus();
                    }
                }

                itemBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    selectItem();
                });

                itemBtn.addEventListener('mouseenter', function () {
                    highlightedIndex = idx;
                    const items = dropdown.querySelectorAll('button.product-result-item');
                    updateHighlight(items);
                });

                dropdown.appendChild(itemBtn);
            });

            dropdown.classList.remove('d-none');
        }

        searchInput.addEventListener('input', function () {
            renderMatches(this.value.trim());
        });

        searchInput.addEventListener('focus', function () {
            if (this.value.trim().length > 0) {
                renderMatches(this.value.trim());
            }
        });

        searchInput.addEventListener('keydown', function (e) {
            const items = dropdown.querySelectorAll('button.product-result-item');
            const isVisible = !dropdown.classList.contains('d-none') && items.length > 0;

            if (e.key === 'ArrowDown' || e.keyCode === 40) {
                if (isVisible) {
                    e.preventDefault();
                    if (highlightedIndex < items.length - 1) {
                        highlightedIndex++;
                    } else {
                        highlightedIndex = 0;
                    }
                    updateHighlight(items);
                }
            } else if (e.key === 'ArrowUp' || e.keyCode === 38) {
                if (isVisible) {
                    e.preventDefault();
                    if (highlightedIndex > 0) {
                        highlightedIndex--;
                    } else {
                        highlightedIndex = items.length - 1;
                    }
                    updateHighlight(items);
                }
            } else if (e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault(); // Prevenir estrictamente el submit accidental del formulario
                if (isVisible) {
                    const targetBtn = (highlightedIndex >= 0 && items[highlightedIndex])
                        ? items[highlightedIndex]
                        : items[0];
                    if (targetBtn) {
                        targetBtn.click();
                    }
                }
            } else if (e.key === 'Escape' || e.keyCode === 27) {
                dropdown.classList.add('d-none');
                highlightedIndex = -1;
            }
        });

        document.addEventListener('click', function (e) {
            if (!row.contains(e.target)) {
                dropdown.classList.add('d-none');
                highlightedIndex = -1;
            }
        });

        select.addEventListener('change', function () {
            const opt = select.options[select.selectedIndex];
            if (opt && opt.value) {
                searchInput.value = opt.textContent.trim();
            }
            dropdown.classList.add('d-none');
            highlightedIndex = -1;
        });
    }

    // Inicializar controles en filas existentes
    document.querySelectorAll('.new-item-row').forEach(row => {
        attachQuantityControls(row);
        attachProductSearch(row);
    });

    // 1. Manejo de líneas dinámicas para agregar platos
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

            attachQuantityControls(row);
            attachProductSearch(row);

            container.appendChild(clone);
            rowIndex++;
        });
    }

    // 2. Lógica Reactiva de la Pasarela de Liquidación / Cobro
    const subtotal = {{ (float)$order->subtotal }};
    const deliveryFee = {{ (float)$order->delivery_fee }};
    const discountInput = document.getElementById('settlement_discount_pct');
    const displaySubtotal = document.getElementById('display_subtotal');
    const displayDiscount = document.getElementById('display_discount');
    const displayDiscountLabel = document.getElementById('display_discount_pct_label');
    const displayTotal = document.getElementById('display_total');

    const switchServiceFee = document.getElementById('switch_service_fee');
    const serviceFeeContainer = document.getElementById('service_fee_container');
    const serviceFeePctInput = document.getElementById('service_fee_pct');
    const serviceFeeInput = document.getElementById('settlement_service_fee');
    const rowServiceFee = document.getElementById('row_service_fee');
    const displayServiceFee = document.getElementById('display_service_fee');
    const displayServicePctLabel = document.getElementById('display_service_pct_label');

    const switchTaxInc = document.getElementById('switch_tax_inc');
    const taxIncContainer = document.getElementById('tax_inc_container');
    const taxIncInput = document.getElementById('settlement_tax_inc');
    const rowTaxInc = document.getElementById('row_tax_inc');
    const displayTaxInc = document.getElementById('display_tax_inc');

    const paymentsContainer = document.getElementById('paymentsContainer');
    const btnAddPaymentLine = document.getElementById('btnAddPaymentLine');
    const balanceAlert = document.getElementById('paymentBalanceAlert');
    const balanceMessage = document.getElementById('paymentBalanceMessage');
    const btnSubmitSettlement = document.getElementById('btnSubmitSettlement');
    const quickCashButtons = document.querySelectorAll('.quick-cash-btn');

    let currentTotal = Math.max(0, Math.round((subtotal + deliveryFee) * 100) / 100);
    let paymentLineCount = 1;

    function formatMoney(amount) {
        return '$' + amount.toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function recalculateTotal(source) {
        // Blindaje normativo colombiano de liquidación (Gastronomía y Restaurantes):
        // 1. Base Gravable / Consumo neto = Subtotal - Descuento (Art. 512-1 del Estatuto Tributario).
        // 2. Propina Voluntaria / Servicio (Ley 1935 de 2018 y Circular Única SIC):
        //    Liberalidad de los trabajadores. NO hace parte de la base gravable de INC ni IVA (no causa impuestos).
        // 3. Impuesto Nacional al Consumo - INC 8% (Art. 512-1 E.T.):
        //    Grava exclusivamente el consumo neto de alimentos y bebidas. NO grava propina ni domicilio.
        // 4. Total a Liquidar = Base Gravable + Domicilio + Servicio + INC.
        const discountPct = parseFloat(discountInput?.value || 0) || 0;
        const discountAmount = Math.round(subtotal * (discountPct / 100) * 100) / 100;
        const netFoodBase = Math.max(0, Math.round((subtotal - discountAmount) * 100) / 100);

        // Servicio / Propina (Ley 1935 de 2018 - 10% sugerido editable o monto en pesos)
        let serviceFee = 0;
        if (switchServiceFee?.checked) {
            serviceFeeContainer?.classList.remove('d-none');
            if (source === 'service_amount') {
                serviceFee = Math.max(0, parseFloat(serviceFeeInput?.value || 0) || 0);
                if (netFoodBase > 0) {
                    const derivedPct = Math.round((serviceFee / netFoodBase) * 1000) / 10;
                    if (serviceFeePctInput) serviceFeePctInput.value = derivedPct;
                }
            } else {
                const servicePct = parseFloat(serviceFeePctInput?.value || 10) || 0;
                serviceFee = Math.round(netFoodBase * (servicePct / 100) * 100) / 100;
                if (serviceFeeInput) serviceFeeInput.value = serviceFee.toFixed(2);
            }
            if (rowServiceFee) rowServiceFee.style.setProperty('display', 'flex', 'important');
            if (displayServiceFee) displayServiceFee.textContent = '+' + formatMoney(serviceFee);
            if (displayServicePctLabel) displayServicePctLabel.textContent = (parseFloat(serviceFeePctInput?.value || 10)) + '%';
        } else {
            serviceFeeContainer?.classList.add('d-none');
            if (serviceFeeInput) serviceFeeInput.value = '0.00';
            if (rowServiceFee) rowServiceFee.style.setProperty('display', 'none', 'important');
        }

        // Impuesto Nacional al Consumo (INC 8%)
        let taxInc = 0;
        if (switchTaxInc?.checked) {
            taxIncContainer?.classList.remove('d-none');
            taxInc = Math.round(netFoodBase * 0.08 * 100) / 100;
            if (taxIncInput) taxIncInput.value = taxInc.toFixed(2);
            if (rowTaxInc) rowTaxInc.style.setProperty('display', 'flex', 'important');
            if (displayTaxInc) displayTaxInc.textContent = '+' + formatMoney(taxInc);
        } else {
            taxIncContainer?.classList.add('d-none');
            if (taxIncInput) taxIncInput.value = '0.00';
            if (rowTaxInc) rowTaxInc.style.setProperty('display', 'none', 'important');
        }

        currentTotal = Math.max(0, Math.round((subtotal - discountAmount + deliveryFee + serviceFee + taxInc) * 100) / 100);

        if (displayDiscount) {
            displayDiscount.textContent = '-' + formatMoney(discountAmount);
        }
        if (displayDiscountLabel) {
            displayDiscountLabel.textContent = discountPct + '%';
        }
        if (displayTotal) {
            displayTotal.textContent = formatMoney(currentTotal);
        }

        // Si solo hay una línea de pago, sincronizarla con el nuevo total
        const lines = paymentsContainer?.querySelectorAll('.payment-line') || [];
        if (lines.length === 1) {
            const firstAmtInput = lines[0].querySelector('.payment-amount-input');
            const firstCashRecInput = lines[0].querySelector('.payment-cash-received-input');
            if (firstAmtInput) firstAmtInput.value = currentTotal.toFixed(2);
            if (firstCashRecInput && parseFloat(firstCashRecInput.value || 0) <= currentTotal) {
                firstCashRecInput.value = currentTotal.toFixed(2);
            }
        }

        recalculatePaymentsBalance();
    }

    function recalculatePaymentsBalance() {
        if (!paymentsContainer) return;

        const lines = paymentsContainer.querySelectorAll('.payment-line');
        let sumPayments = 0;
        let hasCashError = false;

        lines.forEach(line => {
            const methodSelect = line.querySelector('.payment-method-select');
            const amtInput = line.querySelector('.payment-amount-input');
            const cashRecInput = line.querySelector('.payment-cash-received-input');
            const changeDisplay = line.querySelector('.payment-change-display');
            const changeGivenInput = line.querySelector('.payment-change-given-input');

            const amt = parseFloat(amtInput?.value || 0) || 0;
            sumPayments += Math.round(amt * 100);

            if (methodSelect?.value === 'cash') {
                const rec = parseFloat(cashRecInput?.value || amt) || amt;
                const change = Math.max(0, Math.round((rec - amt) * 100) / 100);
                if (changeDisplay) changeDisplay.textContent = 'Vuelto: ' + formatMoney(change);
                if (changeGivenInput) changeGivenInput.value = change.toFixed(2);

                if (rec < amt) {
                    hasCashError = true;
                    if (changeDisplay) changeDisplay.textContent = 'Falta dinero';
                }
            }
        });

        const totalCents = Math.round(currentTotal * 100);
        const diffCents = totalCents - sumPayments;
        const diff = diffCents / 100;

        if (diff !== 0 || hasCashError) {
            if (balanceAlert) {
                balanceAlert.classList.remove('d-none');
                if (hasCashError) {
                    balanceMessage.textContent = 'El efectivo recibido no puede ser inferior al monto asignado.';
                } else if (diff > 0) {
                    balanceMessage.textContent = `Faltan ${formatMoney(diff)} para completar el total de ${formatMoney(currentTotal)}.`;
                } else {
                    balanceMessage.textContent = `Los medios de pago exceden el total por ${formatMoney(Math.abs(diff))}.`;
                }
            }
            if (btnSubmitSettlement) btnSubmitSettlement.disabled = true;
        } else {
            if (balanceAlert) balanceAlert.classList.add('d-none');
            if (btnSubmitSettlement) btnSubmitSettlement.disabled = false;
        }
    }

    function attachPaymentLineEvents(line) {
        const methodSelect = line.querySelector('.payment-method-select');
        const amtInput = line.querySelector('.payment-amount-input');
        const cashRecInput = line.querySelector('.payment-cash-received-input');
        const cashFieldsCol = line.querySelector('.cash-fields-col');
        const changeDisplayCol = line.querySelector('.change-display-col');
        const refRow = line.querySelector('.reference-row');
        const removeBtn = line.querySelector('.remove-payment-btn');

        function updateMethodVisibility() {
            const isCash = methodSelect.value === 'cash';
            if (isCash) {
                if (cashFieldsCol) cashFieldsCol.style.display = 'block';
                if (changeDisplayCol) changeDisplayCol.style.display = 'block';
                if (refRow) refRow.style.display = 'none';
            } else {
                if (cashFieldsCol) cashFieldsCol.style.display = 'none';
                if (changeDisplayCol) changeDisplayCol.style.display = 'none';
                if (refRow) refRow.style.display = 'block';
            }
            recalculatePaymentsBalance();
        }

        methodSelect?.addEventListener('change', updateMethodVisibility);
        amtInput?.addEventListener('input', recalculatePaymentsBalance);
        cashRecInput?.addEventListener('input', recalculatePaymentsBalance);

        removeBtn?.addEventListener('click', function () {
            line.remove();
            updateRemoveButtons();
            recalculatePaymentsBalance();
        });

        updateMethodVisibility();
    }

    function updateRemoveButtons() {
        const lines = paymentsContainer?.querySelectorAll('.payment-line') || [];
        lines.forEach(line => {
            const btn = line.querySelector('.remove-payment-btn');
            if (btn) btn.disabled = (lines.length <= 1);
        });
    }

    if (discountInput) {
        discountInput.addEventListener('input', () => recalculateTotal('discount'));
    }

    switchServiceFee?.addEventListener('change', () => recalculateTotal('service_switch'));
    serviceFeePctInput?.addEventListener('input', () => recalculateTotal('service_pct'));
    serviceFeeInput?.addEventListener('input', () => recalculateTotal('service_amount'));

    switchTaxInc?.addEventListener('change', () => recalculateTotal('tax_switch'));

    // Inicializar primera línea de pago
    const initialLine = paymentsContainer?.querySelector('.payment-line');
    if (initialLine) {
        attachPaymentLineEvents(initialLine);
    }

    // Agregar nueva línea de pago
    if (btnAddPaymentLine && paymentsContainer) {
        btnAddPaymentLine.addEventListener('click', function () {
            const lines = paymentsContainer.querySelectorAll('.payment-line');
            if (lines.length >= 5) {
                alert('Se permite un máximo de 5 medios de pago por venta.');
                return;
            }

            // Calcular saldo pendiente para prellenar
            let currentSum = 0;
            lines.forEach(l => {
                const amt = parseFloat(l.querySelector('.payment-amount-input')?.value || 0) || 0;
                currentSum += amt;
            });
            const remaining = Math.max(0, Math.round((currentTotal - currentSum) * 100) / 100);

            const idx = paymentLineCount++;
            const newLine = document.createElement('div');
            newLine.className = 'payment-line card border p-2 mb-2 rounded-3 bg-white';
            newLine.setAttribute('data-index', idx);
            newLine.innerHTML = `
                <div class="row g-2 align-items-center">
                    <div class="col-12 col-md-3">
                        <select name="payments[${idx}][method]" class="form-select form-select-sm payment-method-select" required>
                            <option value="transfer" selected>Transferencia / Nequi</option>
                            <option value="card">Tarjeta</option>
                            <option value="cash">Efectivo</option>
                            <option value="other">Otro</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">$</span>
                            <input type="number" step="0.01" min="0.01" name="payments[${idx}][amount]" class="form-control text-end payment-amount-input fw-bold" value="${remaining.toFixed(2)}" required>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 cash-fields-col" style="display: none;">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text" title="Efectivo Recibido"><i class="bi bi-box-arrow-in-down"></i></span>
                            <input type="number" step="0.01" min="0" name="payments[${idx}][cash_received]" class="form-control text-end payment-cash-received-input" placeholder="Recibido" value="${remaining.toFixed(2)}">
                        </div>
                    </div>
                    <div class="col-10 col-md-2 text-end text-md-center change-display-col" style="display: none;">
                        <span class="badge bg-light text-dark border small payment-change-display">Vuelto: $0.00</span>
                        <input type="hidden" name="payments[${idx}][change_given]" class="payment-change-given-input" value="0.00">
                    </div>
                    <div class="col-2 col-md-1 text-end">
                        <button type="button" class="btn btn-sm btn-outline-danger remove-payment-btn">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
                <div class="row g-2 mt-1 reference-row">
                    <div class="col-12">
                        <input type="text" name="payments[${idx}][reference]" class="form-control form-control-sm payment-reference-input" placeholder="N° Comprobante / Referencia (opcional)" maxlength="60">
                    </div>
                </div>
            `;

            paymentsContainer.appendChild(newLine);
            attachPaymentLineEvents(newLine);
            updateRemoveButtons();
            recalculatePaymentsBalance();
        });
    }

    // Atajos de efectivo
    quickCashButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            const firstCashLine = Array.from(paymentsContainer.querySelectorAll('.payment-line'))
                .find(l => l.querySelector('.payment-method-select')?.value === 'cash');

            if (!firstCashLine) {
                alert('No hay ninguna línea de efectivo activa.');
                return;
            }

            const cashRecInput = firstCashLine.querySelector('.payment-cash-received-input');
            const amtInput = firstCashLine.querySelector('.payment-amount-input');
            const type = this.getAttribute('data-type');

            if (type === 'exact') {
                const amt = parseFloat(amtInput?.value || currentTotal);
                if (cashRecInput) cashRecInput.value = amt.toFixed(2);
            } else {
                const val = parseFloat(this.getAttribute('data-amt') || 0);
                if (cashRecInput) cashRecInput.value = val.toFixed(2);
            }

            recalculatePaymentsBalance();
        });
    });

    // Prevención de doble clic al liquidar
    const settlementForm = document.getElementById('settlementForm');
    if (settlementForm) {
        settlementForm.addEventListener('submit', function () {
            if (btnSubmitSettlement) {
                btnSubmitSettlement.disabled = true;
                btnSubmitSettlement.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Facturando...';
            }
        });
    }

    // Apertura automática si la URL contiene el hash #cobrar
    function checkHashForSettlement() {
        if (window.location.hash === '#cobrar') {
            const settlementModalEl = document.getElementById('settlementModal');
            if (settlementModalEl && typeof bootstrap !== 'undefined') {
                const modal = bootstrap.Modal.getOrCreateInstance(settlementModalEl);
                modal.show();
            }
        }
    }

    checkHashForSettlement();
    window.addEventListener('hashchange', checkHashForSettlement);
});
</script>

@include('restaurant.partials.kitchen-config-modal')

@if(session('print_kitchen_ticket_id'))
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            try {
                const autoPrint = localStorage.getItem('puntostock_auto_print_kitchen') !== 'disabled';
                if (autoPrint) {
                    const printUrl = "{{ route('restaurant.orders.kitchen-ticket', session('print_kitchen_ticket_id')) }}";
                    const popup = window.open(printUrl, '_blank', 'width=450,height=650,scrollbars=yes');
                    if (!popup || popup.closed || typeof popup.closed === 'undefined') {
                        console.info('Aviso: La ventana emergente de impresión automática fue bloqueada por el navegador. El botón de impresión manual está disponible.');
                    }
                }
            } catch (e) {
                console.warn('Impresión automática de cocina no ejecutada:', e);
            }
        });
    </script>
@endif
@endsection
