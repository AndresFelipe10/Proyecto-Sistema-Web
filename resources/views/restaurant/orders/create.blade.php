@extends('layouts.app')

@section('title', 'Abrir Comanda')

@section('content')
<div class="container py-4" style="max-width: 800px;">
    <div class="d-flex align-items-center mb-4">
        <a href="{{ route('restaurant.tables.index') }}" class="btn btn-outline-secondary btn-sm me-3">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h1 class="h3 fw-bold mb-0">Abrir Nueva Comanda</h1>
    </div>

    @if($tables->isEmpty())
        <div class="alert alert-warning shadow-sm rounded-4 p-4 text-center">
            <i class="bi bi-exclamation-triangle fs-3 d-block mb-2"></i>
            <h5 class="fw-bold">No hay mesas libres disponibles</h5>
            <p class="mb-3">Todas las mesas se encuentran ocupadas o no hay mesas activas configuradas en el salón.</p>
            <a href="{{ route('restaurant.tables.index') }}" class="btn btn-primary">
                Ver Salón de Mesas
            </a>
        </div>
    @else
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <form action="{{ route('restaurant.orders.store') }}" method="POST" id="orderForm">
                    @csrf

                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-6">
                            <label for="table_id" class="form-label fw-bold">Seleccionar Mesa <span class="text-danger">*</span></label>
                            <select class="form-select @error('table_id') is-invalid @enderror" id="table_id" name="table_id" required>
                                <option value="" disabled {{ old('table_id', $selectedTableId) ? '' : 'selected' }}>-- Selecciona una mesa disponible --</option>
                                @foreach($tables as $table)
                                    <option value="{{ $table->id }}" {{ (string) old('table_id', $selectedTableId) === (string) $table->id ? 'selected' : '' }}>
                                        {{ $table->name }} (Capacidad: {{ $table->capacity }} personas)
                                    </option>
                                @endforeach
                            </select>
                            @error('table_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="customer_name" class="form-label fw-bold">Nombre del Cliente / Referencia</label>
                            <input type="text" class="form-control @error('customer_name') is-invalid @enderror" id="customer_name" name="customer_name" value="{{ old('customer_name') }}" placeholder="Opcional: Ej. Juan Pérez">
                            @error('customer_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label for="notes" class="form-label fw-bold">Observaciones Generales</label>
                            <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="2" placeholder="Opcional: Ubicación de mesa, preferencia especial, etc.">{{ old('notes') }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Sección de Ítems Iniciales --}}
                    <div class="border-top pt-4 mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold mb-0">Platos o Productos Iniciales (Opcional)</h5>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-item">
                                <i class="bi bi-plus-circle me-1"></i> Agregar Plato
                            </button>
                        </div>

                        <div id="items-container">
                            {{-- Fila inicial vacía --}}
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                        <a href="{{ route('restaurant.tables.index') }}" class="btn btn-light px-4">Cancelar</a>
                        <button type="submit" class="btn btn-primary px-4 fw-bold">
                            <i class="bi bi-check-lg me-1"></i> Abrir Comanda
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

<template id="item-row-template">
    <div class="card bg-light border-0 mb-3 item-row p-3 rounded-3">
        <div class="row g-2 align-items-center">
            <div class="col-12 col-md-5">
                <label class="form-label small fw-semibold">Plato / Producto</label>
                <select class="form-select form-select-sm product-select" name="items[INDEX][product_id]" required>
                    <option value="" disabled selected>-- Selecciona un plato --</option>
                    @foreach($products as $prod)
                        <option value="{{ $prod->id }}" data-price="{{ $prod->sale_price }}">
                            {{ $prod->name }} ({{ $prod->isDish() ? 'Plato' : 'Bebida/Prod' }} - ${{ number_format($prod->sale_price, 2) }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold">Cantidad</label>
                <input type="number" step="any" min="0.001" value="1" class="form-control form-select-sm" name="items[INDEX][quantity]" required>
            </div>
            <div class="col-6 col-md-4">
                <label class="form-label small fw-semibold">Observación / Modificación</label>
                <input type="text" class="form-control form-select-sm" name="items[INDEX][notes]" placeholder="Ej: Sin cebolla, término 3/4">
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
    const container = document.getElementById('items-container');
    const template = document.getElementById('item-row-template');
    const addBtn = document.getElementById('btn-add-item');
    let itemIndex = 0;

    function addItemRow() {
        if (!template || !container) return;
        const clone = template.content.cloneNode(true);
        const row = clone.querySelector('.item-row');
        row.innerHTML = row.innerHTML.replace(/INDEX/g, itemIndex);
        
        row.querySelector('.remove-item-btn').addEventListener('click', function () {
            row.remove();
        });

        container.appendChild(clone);
        itemIndex++;
    }

    if (addBtn) {
        addBtn.addEventListener('click', addItemRow);
    }
});
</script>
@endsection
