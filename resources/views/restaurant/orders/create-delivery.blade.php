@extends('layouts.app')

@section('title', 'Nuevo Pedido Domicilio / Para Llevar')

@section('content')
<div class="container py-4" style="max-width: 900px;">
    <div class="d-flex align-items-center mb-4">
        <a href="{{ route('restaurant.orders.deliveries') }}" class="btn btn-outline-secondary btn-sm me-3">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h1 class="h3 fw-bold mb-0">Nuevo Pedido a Domicilio / Para Llevar</h1>
            <p class="text-muted small mb-0">Registra pedidos de entrega o recogida en el restaurante con tirilla de despacho.</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <form action="{{ route('restaurant.orders.store-delivery') }}" method="POST" id="deliveryForm">
                @csrf

                {{-- Selector Tipo de Pedido --}}
                <div class="mb-4">
                    <label class="form-label fw-bold d-block">Tipo de Pedido <span class="text-danger">*</span></label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="order_type" id="type_delivery" value="delivery" {{ old('order_type', 'delivery') === 'delivery' ? 'checked' : '' }}>
                        <label class="btn btn-outline-primary py-2 fw-semibold" for="type_delivery">
                            <i class="bi bi-bicycle me-1"></i> Domicilio
                        </label>

                        <input type="radio" class="btn-check" name="order_type" id="type_takeout" value="takeout" {{ old('order_type') === 'takeout' ? 'checked' : '' }}>
                        <label class="btn btn-outline-primary py-2 fw-semibold" for="type_takeout">
                            <i class="bi bi-bag-check me-1"></i> Para Llevar / Recoger
                        </label>
                    </div>
                </div>

                {{-- Datos del Cliente --}}
                <div class="border rounded-3 p-3 mb-4 bg-light">
                    <h5 class="fw-bold mb-3 text-dark">
                        <i class="bi bi-person-fill text-primary me-2"></i>Datos del Cliente y Entrega
                    </h5>

                    <input type="hidden" name="customer_id" id="customer_id" value="{{ old('customer_id') }}">

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label for="customer_name" class="form-label fw-bold small">Nombre del Cliente <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('customer_name') is-invalid @enderror" id="customer_name" name="customer_name" value="{{ old('customer_name') }}" placeholder="Ej: María Camila López" required>
                            @error('customer_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="delivery_phone" class="form-label fw-bold small" id="phone_label">Teléfono / WhatsApp <span class="text-danger delivery-req">*</span></label>
                            <input type="text" class="form-control @error('delivery_phone') is-invalid @enderror" id="delivery_phone" name="delivery_phone" value="{{ old('delivery_phone') }}" placeholder="Ej: 315 123 4567">
                            @error('delivery_phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12" id="address_group">
                            <label for="delivery_address" class="form-label fw-bold small">Dirección Exacta de Entrega <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('delivery_address') is-invalid @enderror" id="delivery_address" name="delivery_address" value="{{ old('delivery_address') }}" placeholder="Ej: Cra 100 # 15-20, Apto 302, Barrio San Fernando">
                            @error('delivery_address')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-8">
                            <label for="delivery_notes" class="form-label fw-bold small">Instrucciones de Entrega / Referencia</label>
                            <input type="text" class="form-control @error('delivery_notes') is-invalid @enderror" id="delivery_notes" name="delivery_notes" value="{{ old('delivery_notes') }}" placeholder="Ej: Timbre blanco, dejar en portería, edificio frente al parque">
                            @error('delivery_notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-4" id="fee_group">
                            <label for="delivery_fee" class="form-label fw-bold small">Costo de Domicilio ($)</label>
                            <input type="number" step="100" min="0" class="form-control @error('delivery_fee') is-invalid @enderror" id="delivery_fee" name="delivery_fee" value="{{ old('delivery_fee', '0') }}">
                            @error('delivery_fee')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Sección de Ítems --}}
                <div class="border-top pt-3 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0 text-dark">
                            <i class="bi bi-cart-plus-fill text-primary me-2"></i>Platos o Productos
                        </h5>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-item">
                            <i class="bi bi-plus-circle me-1"></i> Agregar Plato
                        </button>
                    </div>

                    <div id="items-container">
                        {{-- Primera línea predeterminada --}}
                        <div class="card bg-light border-0 mb-3 item-row p-3 rounded-3">
                            <div class="row g-2 align-items-center">
                                <div class="col-12 col-md-5">
                                    <label class="form-label small fw-semibold">Plato / Producto</label>
                                    <select class="form-select form-select-sm" name="items[0][product_id]" required>
                                        <option value="" disabled selected>-- Seleccionar plato --</option>
                                        @foreach($products as $prod)
                                            <option value="{{ $prod->id }}">
                                                {{ $prod->name }} (${{ number_format($prod->sale_price, 2) }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6 col-md-2">
                                    <label class="form-label small fw-semibold">Cantidad</label>
                                    <input type="number" step="any" min="0.001" value="1" class="form-control form-select-sm" name="items[0][quantity]" required>
                                </div>
                                <div class="col-6 col-md-4">
                                    <label class="form-label small fw-semibold">Observación</label>
                                    <input type="text" class="form-control form-select-sm" name="items[0][notes]" placeholder="Ej: Sin cebolla, extra salsa">
                                </div>
                                <div class="col-12 col-md-1 text-end pt-md-4">
                                    <button type="button" class="btn btn-sm btn-outline-danger remove-item-btn" disabled>
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="notes" class="form-label fw-bold small">Notas Generales de la Orden</label>
                    <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Opcional: Paga con billete de $50.000, llevar cambio de $20.000">{{ old('notes') }}</textarea>
                </div>

                <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                    <a href="{{ route('restaurant.orders.deliveries') }}" class="btn btn-light px-4">Cancelar</a>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">
                        <i class="bi bi-check-lg me-1"></i> Registrar Pedido
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<template id="item-row-template">
    <div class="card bg-light border-0 mb-3 item-row p-3 rounded-3">
        <div class="row g-2 align-items-center">
            <div class="col-12 col-md-5">
                <label class="form-label small fw-semibold">Plato / Producto</label>
                <select class="form-select form-select-sm" name="items[INDEX][product_id]" required>
                    <option value="" disabled selected>-- Seleccionar plato --</option>
                    @foreach($products as $prod)
                        <option value="{{ $prod->id }}">
                            {{ $prod->name }} (${{ number_format($prod->sale_price, 2) }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold">Cantidad</label>
                <input type="number" step="any" min="0.001" value="1" class="form-control form-select-sm" name="items[INDEX][quantity]" required>
            </div>
            <div class="col-6 col-md-4">
                <label class="form-label small fw-semibold">Observación</label>
                <input type="text" class="form-control form-select-sm" name="items[INDEX][notes]" placeholder="Ej: Sin cebolla, extra salsa">
            </div>
            <div class="col-12 col-md-1 text-end pt-md-4">
                <button type="button" class="btn btn-sm btn-outline-danger remove-item-btn">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>
    </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const typeDelivery = document.getElementById('type_delivery');
    const typeTakeout = document.getElementById('type_takeout');
    const addressGroup = document.getElementById('address_group');
    const feeGroup = document.getElementById('fee_group');
    const deliveryAddress = document.getElementById('delivery_address');
    const deliveryFee = document.getElementById('delivery_fee');

    function syncOrderType() {
        if (typeTakeout.checked) {
            addressGroup.style.display = 'none';
            feeGroup.style.display = 'none';
            deliveryAddress.removeAttribute('required');
            deliveryFee.value = '0';
        } else {
            addressGroup.style.display = 'block';
            feeGroup.style.display = 'block';
            deliveryAddress.setAttribute('required', 'required');
        }
    }

    if (typeDelivery && typeTakeout) {
        typeDelivery.addEventListener('change', syncOrderType);
        typeTakeout.addEventListener('change', syncOrderType);
        syncOrderType();
    }

    // Dynamic items
    const container = document.getElementById('items-container');
    const template = document.getElementById('item-row-template');
    const addBtn = document.getElementById('btn-add-item');
    let itemIndex = 1;

    if (addBtn && template && container) {
        addBtn.addEventListener('click', function () {
            const clone = template.content.cloneNode(true);
            const row = clone.querySelector('.item-row');
            row.innerHTML = row.innerHTML.replace(/INDEX/g, itemIndex);
            
            row.querySelector('.remove-item-btn').addEventListener('click', function () {
                row.remove();
            });

            container.appendChild(clone);
            itemIndex++;
        });
    }
});
</script>
@endsection
