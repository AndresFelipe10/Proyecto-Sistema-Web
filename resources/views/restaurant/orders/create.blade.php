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

                        <div class="col-12 col-md-3">
                            <label for="customer_name" class="form-label fw-bold">Nombre del Mesero (Opcional)</label>
                            <input type="text" class="form-control @error('customer_name') is-invalid @enderror" id="customer_name" name="customer_name" value="{{ old('customer_name') }}" placeholder="Ej. Juan, Mesa ventana, etc.">
                            @error('customer_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-3">
                            <label for="guest_count" class="form-label fw-bold">Número de Personas (Opcional)</label>
                            <input type="number" name="guest_count" id="guest_count" class="form-control @error('guest_count') is-invalid @enderror" min="1" max="99" value="{{ old('guest_count') }}" placeholder="Ej: 2, 4">
                            @error('guest_count')
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
                            <button type="button" class="btn btn-outline-primary fw-semibold px-3 py-2 d-inline-flex align-items-center" id="btn-add-item" style="min-height: 44px;">
                                <i class="bi bi-plus-circle fs-5 me-2"></i> + Agregar plato
                            </button>
                        </div>

                        <div id="items-container">
                            {{-- Fila inicial vacía --}}
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <a href="{{ route('restaurant.tables.index') }}" class="btn btn-light px-4 py-2 d-inline-flex align-items-center" style="min-height: 44px;">Cancelar</a>
                        <button type="submit" class="btn btn-primary px-4 py-2 fw-bold d-inline-flex align-items-center shadow-sm" style="min-height: 44px;">
                            <i class="bi bi-check-lg fs-5 me-2"></i> Abrir Comanda
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

@php
    $groupedProducts = $products->groupBy(fn($p) => $p->category?->name ?? 'General / Sin Categoría');
@endphp

<template id="item-row-template">
    <div class="card bg-light border-0 mb-3 item-row p-3 rounded-3">
        <div class="row g-2 align-items-center">
            <div class="col-12 col-md-5">
                <label class="form-label small fw-semibold">Plato / Producto <span class="text-danger">*</span></label>
                <div class="position-relative mb-1">
                    <input type="text" class="form-control form-control-sm product-search-input" placeholder="🔍 Escribe para buscar plato o bebida..." autocomplete="off">
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
                    <input type="number" name="items[INDEX][quantity]" class="form-control text-center input-qty" value="1" min="1" max="999" required>
                    <button type="button" class="btn btn-outline-secondary btn-qty-plus fw-bold px-2">+</button>
                </div>
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

    function attachProductSearch(row) {
        const searchInput = row.querySelector('.product-search-input');
        const select = row.querySelector('.product-select');
        const dropdown = row.querySelector('.product-dropdown-list');
        if (!searchInput || !select || !dropdown) return;

        function normalizeStr(str) {
            return (str || '')
                .normalize("NFD")
                .replace(/[\u0300-\u036f]/g, "")
                .toLowerCase();
        }

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

    // Inicializar controles en filas existentes si las hubiera
    document.querySelectorAll('.item-row').forEach(row => {
        attachQuantityControls(row);
        attachProductSearch(row);
    });

    function addItemRow() {
        if (!template || !container) return;
        const clone = template.content.cloneNode(true);
        const row = clone.querySelector('.item-row');
        row.innerHTML = row.innerHTML.replace(/INDEX/g, itemIndex);

        row.querySelector('.remove-item-btn').addEventListener('click', function () {
            row.remove();
        });

        attachQuantityControls(row);
        attachProductSearch(row);

        container.appendChild(clone);
        itemIndex++;
    }

    if (addBtn) {
        addBtn.addEventListener('click', addItemRow);
    }
});
</script>
@endsection
