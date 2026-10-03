@extends('layouts.app')

@section('title', 'Registrar Movimiento de Inventario')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card card-custom p-4 p-md-5 bg-white">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-1">Registrar Movimiento</h3>
                    <p class="text-muted small mb-0">Modifica y audita las existencias de un producto en <strong>{{ $currentBusiness->name }}</strong>.</p>
                </div>
                <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                    <i class="bi bi-arrow-left me-1"></i> Volver
                </a>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger mb-4 py-2 px-3 small rounded-3">
                    <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> Corrige los siguientes errores:</div>
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('inventory.store') }}" novalidate id="inventoryMovementForm">
                @csrf

                <div class="mb-3">
                    <label class="form-label small fw-semibold text-secondary">Producto <span class="text-danger">*</span></label>
                    
                    {{-- 1. Barra de búsqueda rápida sincronizada --}}
                    <div class="position-relative mb-2">
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0">
                                <i class="bi bi-search text-muted"></i>
                            </span>
                            <input type="text" 
                                   id="product_search_input" 
                                   class="form-control border-start-0" 
                                   placeholder="Escribe para buscar producto por nombre o SKU..." 
                                   autocomplete="off">
                            <button type="button" class="btn btn-outline-secondary d-none" id="btn_clear_product_search" title="Limpiar búsqueda">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                        <div id="product_search_results" class="list-group position-absolute w-100 shadow-lg border rounded-3 overflow-auto d-none" 
                             style="max-height: 240px; z-index: 1060; top: 100%; left: 0; background: #fff;"></div>
                    </div>

                    {{-- 2. Dropdown estándar sincronizado --}}
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-box-seam"></i></span>
                        <select class="form-select @error('product_id') is-invalid @enderror" id="product_id" name="product_id" required>
                            <option value="" disabled {{ old('product_id', $selectedProductId) ? '' : 'selected' }}>O selecciona directamente de la lista desplegable...</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" 
                                        data-name="{{ $product->name }}" 
                                        data-sku="{{ $product->sku }}" 
                                        data-stock="{{ $product->stock }}"
                                        {{ old('product_id', $selectedProductId) == $product->id ? 'selected' : '' }}>
                                    {{ $product->name }} (SKU: {{ $product->sku }}) — Stock actual: {{ $product->stock }} unid.
                                </option>
                            @endforeach
                        </select>
                        @error('product_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="type" class="form-label small fw-semibold text-secondary">Tipo de Movimiento <span class="text-danger">*</span></label>
                        <select class="form-select @error('type') is-invalid @enderror" id="type" name="type" required>
                            <option value="entry" {{ old('type') === 'entry' ? 'selected' : '' }}>Entrada / Compra (+)</option>
                            <option value="exit" {{ old('type') === 'exit' ? 'selected' : '' }}>Salida / Merma / Consumo (-)</option>
                            <option value="adjustment" {{ old('type') === 'adjustment' ? 'selected' : '' }}>Ajuste de Conteo Físico (~)</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="quantity" class="form-label small fw-semibold text-secondary">Cantidad / Nuevo Stock <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <button type="button" class="btn btn-outline-secondary px-3" id="btnQtyMinus" title="Disminuir cantidad" style="min-height: 38px;">
                                <i class="bi bi-dash-lg"></i>
                            </button>
                            <input type="number" 
                                   min="0" 
                                   class="form-control text-center fw-bold font-monospace @error('quantity') is-invalid @enderror" 
                                   id="quantity" 
                                   name="quantity" 
                                   value="{{ old('quantity', '1') }}" 
                                   required
                                   style="min-height: 38px;">
                            <button type="button" class="btn btn-outline-secondary px-3" id="btnQtyPlus" title="Aumentar cantidad" style="min-height: 38px;">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        </div>
                        @error('quantity')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                        <div class="form-text small" id="quantityHelper">
                            En entradas/salidas indica las unidades a sumar/restar. En ajustes indica el stock total real.
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="reason" class="form-label small fw-semibold text-secondary">Motivo o Justificación <span class="text-danger">*</span></label>
                    <input type="text" 
                           class="form-control @error('reason') is-invalid @enderror" 
                           id="reason" 
                           name="reason" 
                           value="{{ old('reason') }}" 
                           placeholder="Ej. Compra a proveedor, Producto dañado, Conteo mensual..." 
                           required>
                    @error('reason')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary px-4 rounded-3">Cancelar</a>
                    <button type="submit" class="btn btn-primary px-4 rounded-3 fw-semibold">
                        <i class="bi bi-check-circle-fill me-1"></i> Aplicar y Guardar Movimiento
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const productSelect = document.getElementById('product_id');
    const searchInput = document.getElementById('product_search_input');
    const searchResults = document.getElementById('product_search_results');
    const btnClearSearch = document.getElementById('btn_clear_product_search');
    const quantityInput = document.getElementById('quantity');
    const btnQtyMinus = document.getElementById('btnQtyMinus');
    const btnQtyPlus = document.getElementById('btnQtyPlus');

    const productsData = [];
    if (productSelect) {
        Array.from(productSelect.options).forEach((opt) => {
            if (!opt.value) return;
            productsData.push({
                id: opt.value,
                name: opt.dataset.name || opt.text.split(' (SKU:')[0].trim(),
                sku: opt.dataset.sku || '',
                stock: opt.dataset.stock || '',
                text: opt.text.trim()
            });
        });
    }

    let highlightedIndex = -1;

    function renderMatches(query) {
        if (!searchResults) return;
        searchResults.innerHTML = '';
        highlightedIndex = -1;

        if (!query) {
            searchResults.classList.add('d-none');
            return;
        }

        const q = query.toLowerCase();
        const matches = productsData.filter(p => 
            p.name.toLowerCase().includes(q) || 
            p.sku.toLowerCase().includes(q)
        );

        if (matches.length === 0) {
            const emptyItem = document.createElement('div');
            emptyItem.className = 'list-group-item text-muted small py-2 px-3 text-center';
            emptyItem.textContent = 'No se encontraron productos coincidentes';
            searchResults.appendChild(emptyItem);
            searchResults.classList.remove('d-none');
            return;
        }

        matches.forEach((p, idx) => {
            const itemBtn = document.createElement('button');
            itemBtn.type = 'button';
            itemBtn.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2 px-3 product-result-item';
            itemBtn.dataset.index = idx;

            const leftDiv = document.createElement('div');
            leftDiv.className = 'text-start text-truncate me-2';
            leftDiv.innerHTML = `<strong class="d-block text-dark text-truncate">${p.name}</strong>` +
                (p.sku ? `<small class="text-muted font-monospace">SKU: ${p.sku}</small>` : '');

            const rightDiv = document.createElement('div');
            rightDiv.className = 'text-end flex-shrink-0';
            rightDiv.innerHTML = `<span class="badge bg-light text-dark border font-monospace">Stock: ${p.stock}</span>`;

            itemBtn.appendChild(leftDiv);
            itemBtn.appendChild(rightDiv);

            function chooseItem() {
                productSelect.value = p.id;
                productSelect.dispatchEvent(new Event('change'));
                searchInput.value = p.name;
                if (btnClearSearch) btnClearSearch.classList.remove('d-none');
                searchResults.classList.add('d-none');
                highlightedIndex = -1;

                if (quantityInput) {
                    quantityInput.focus();
                    try { quantityInput.select(); } catch(e) {}
                }
            }

            itemBtn.addEventListener('click', function (e) {
                e.preventDefault();
                chooseItem();
            });

            itemBtn.addEventListener('mouseenter', function () {
                highlightedIndex = idx;
                updateHighlight();
            });

            searchResults.appendChild(itemBtn);
        });

        searchResults.classList.remove('d-none');
    }

    function updateHighlight() {
        if (!searchResults) return;
        const items = searchResults.querySelectorAll('button.product-result-item');
        items.forEach((btn, idx) => {
            if (idx === highlightedIndex) {
                btn.classList.add('active', 'bg-primary', 'text-white');
                btn.querySelectorAll('.text-dark, .text-muted').forEach(el => {
                    el.classList.add('text-white');
                    el.classList.remove('text-dark', 'text-muted');
                });
                btn.scrollIntoView({ block: 'nearest' });
            } else {
                btn.classList.remove('active', 'bg-primary', 'text-white');
                btn.querySelectorAll('.text-white').forEach(el => {
                    el.classList.remove('text-white');
                });
            }
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            renderMatches(this.value.trim());
            if (btnClearSearch) {
                if (this.value.trim()) {
                    btnClearSearch.classList.remove('d-none');
                } else {
                    btnClearSearch.classList.add('d-none');
                }
            }
        });

        searchInput.addEventListener('keydown', function (e) {
            if (!searchResults) return;
            const items = searchResults.querySelectorAll('button.product-result-item');
            const isVisible = !searchResults.classList.contains('d-none') && items.length > 0;

            if (e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault(); // Prevenir submit accidental
                if (isVisible) {
                    const target = (highlightedIndex >= 0 && items[highlightedIndex]) ? items[highlightedIndex] : items[0];
                    if (target) {
                        target.click();
                    }
                } else if (quantityInput) {
                    quantityInput.focus();
                    try { quantityInput.select(); } catch(e) {}
                }
            } else if (e.key === 'ArrowDown' || e.keyCode === 40) {
                if (isVisible) {
                    e.preventDefault();
                    highlightedIndex = (highlightedIndex + 1) % items.length;
                    updateHighlight();
                }
            } else if (e.key === 'ArrowUp' || e.keyCode === 38) {
                if (isVisible) {
                    e.preventDefault();
                    highlightedIndex = (highlightedIndex - 1 + items.length) % items.length;
                    updateHighlight();
                }
            } else if (e.key === 'Escape' || e.keyCode === 27) {
                searchResults.classList.add('d-none');
                highlightedIndex = -1;
            }
        });
    }

    if (btnClearSearch) {
        btnClearSearch.addEventListener('click', function () {
            searchInput.value = '';
            btnClearSearch.classList.add('d-none');
            if (searchResults) searchResults.classList.add('d-none');
            if (productSelect) {
                productSelect.value = '';
                productSelect.dispatchEvent(new Event('change'));
            }
            if (searchInput) searchInput.focus();
        });
    }

    document.addEventListener('click', function (e) {
        if (searchInput && searchResults && !searchInput.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.classList.add('d-none');
        }
    });

    // Sincronización desde el dropdown <select> hacia el input
    if (productSelect) {
        productSelect.addEventListener('change', function () {
            const opt = productSelect.options[productSelect.selectedIndex];
            if (opt && opt.value) {
                if (searchInput) searchInput.value = opt.dataset.name || opt.text.split(' (SKU:')[0].trim();
                if (btnClearSearch) btnClearSearch.classList.remove('d-none');
            } else {
                if (searchInput) searchInput.value = '';
                if (btnClearSearch) btnClearSearch.classList.add('d-none');
            }
            if (searchResults) searchResults.classList.add('d-none');
        });

        // Pre-cargar si ya hay producto pre-seleccionado
        if (productSelect.value) {
            const selectedOpt = productSelect.options[productSelect.selectedIndex];
            if (selectedOpt && selectedOpt.value) {
                if (searchInput) searchInput.value = selectedOpt.dataset.name || selectedOpt.text.split(' (SKU:')[0].trim();
                if (btnClearSearch) btnClearSearch.classList.remove('d-none');
            }
        }
    }

    // Controles de cantidad: auto-selección en foco y steppers
    if (quantityInput) {
        quantityInput.addEventListener('focus', function () {
            this.select();
        });

        if (btnQtyMinus) {
            btnQtyMinus.addEventListener('click', function () {
                let currentVal = parseFloat(quantityInput.value) || 0;
                quantityInput.value = Math.max(0, currentVal - 1);
            });
        }

        if (btnQtyPlus) {
            btnQtyPlus.addEventListener('click', function () {
                let currentVal = parseFloat(quantityInput.value) || 0;
                quantityInput.value = currentVal + 1;
            });
        }
    }
});
</script>
@endsection

