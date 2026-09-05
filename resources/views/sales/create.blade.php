@extends('layouts.app')

@section('title', 'Nueva Venta')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h3 class="fw-bold mb-1">Nueva Venta</h3>
        <p class="text-muted mb-0">Registrar una venta de productos</p>
    </div>
    <a href="{{ route('sales.index') }}" class="btn btn-outline-secondary rounded-pill px-3">
        <i class="bi bi-arrow-left me-1"></i> Volver al Historial
    </a>
</div>

@if ($errors->any())
    <div class="alert alert-danger rounded-3 mb-4">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong>Error:</strong>
        <ul class="mb-0 mt-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('sales.store') }}" id="saleForm">
    @csrf
    <div class="row g-4">
        {{-- Panel izquierdo: Buscar y agregar productos --}}
        <div class="col-lg-8">
            <div class="card card-custom p-4 mb-4">
                <h5 class="fw-bold mb-3 border-bottom pb-2"><i class="bi bi-search me-2 text-primary"></i>Agregar Productos</h5>

                <div class="input-group mb-3">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-upc-scan text-muted"></i></span>
                    <input type="text" id="productSearch" class="form-control border-start-0"
                           placeholder="Buscar por nombre o SKU..." autocomplete="off">
                </div>

                {{-- Resultados de búsqueda --}}
                <div id="searchResults" class="list-group mb-3" style="display:none; max-height:220px; overflow-y:auto;"></div>

                {{-- Tabla del carrito --}}
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="cartTable">
                        <thead class="bg-light">
                            <tr>
                                <th>Producto</th>
                                <th class="text-center" style="width:100px;">Stock</th>
                                <th class="text-center" style="width:110px;">Cantidad</th>
                                <th class="text-end" style="width:140px;">P. Unitario</th>
                                <th class="text-end" style="width:140px;">Subtotal</th>
                                <th class="text-center" style="width:60px;"></th>
                            </tr>
                        </thead>
                        <tbody id="cartBody">
                            <tr id="emptyCartRow">
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="bi bi-cart3 fs-3 d-block mb-1 opacity-50"></i>
                                    Busque y agregue productos al carrito
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Panel derecho: Resumen y datos de la venta --}}
        <div class="col-lg-4">
            <div class="card card-custom p-4 mb-4">
                <h5 class="fw-bold mb-3 border-bottom pb-2"><i class="bi bi-receipt me-2 text-primary"></i>Datos de la Venta</h5>

                <div class="mb-3">
                    <label for="sale_date" class="form-label small fw-semibold text-muted">Fecha y Hora</label>
                    <input type="datetime-local" id="sale_date" name="sale_date" class="form-control"
                           value="{{ old('sale_date', now()->format('Y-m-d\TH:i')) }}" required>
                </div>

                <div class="mb-3">
                    <label for="customer_id" class="form-label small fw-semibold text-muted">Cliente (opcional)</label>
                    <select id="customer_id" name="customer_id" class="form-select">
                        <option value="">— Venta al mostrador —</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" {{ old('customer_id') == $customer->id ? 'selected' : '' }}>
                                {{ $customer->name }} {{ $customer->identification_number ? '(' . $customer->identification_number . ')' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label for="payment_method" class="form-label small fw-semibold text-muted">Método de Pago</label>
                    <select id="payment_method" name="payment_method" class="form-select" required>
                        <option value="cash" {{ old('payment_method', 'cash') === 'cash' ? 'selected' : '' }}>Efectivo</option>
                        <option value="transfer" {{ old('payment_method') === 'transfer' ? 'selected' : '' }}>Transferencia</option>
                        <option value="card" {{ old('payment_method') === 'card' ? 'selected' : '' }}>Tarjeta</option>
                        <option value="other" {{ old('payment_method') === 'other' ? 'selected' : '' }}>Otro</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="discount" class="form-label small fw-semibold text-muted">Descuento ($)</label>
                    <input type="number" id="discount" name="discount" class="form-control"
                           value="{{ old('discount', 0) }}" min="0" step="any">
                </div>

                <div class="mb-3">
                    <label for="notes" class="form-label small fw-semibold text-muted">Notas</label>
                    <textarea id="notes" name="notes" class="form-control" rows="2" maxlength="1000"
                              placeholder="Notas adicionales...">{{ old('notes') }}</textarea>
                </div>
            </div>

            {{-- Totales --}}
            <div class="card card-custom p-4 mb-4">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Subtotal</span>
                    <span class="fw-semibold" id="displaySubtotal">$0</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Descuento</span>
                    <span class="fw-semibold text-danger" id="displayDiscount">-$0</span>
                </div>
                <hr>
                <div class="d-flex justify-content-between">
                    <span class="fw-bold fs-5">Total</span>
                    <span class="fw-bold fs-5 text-primary" id="displayTotal">$0</span>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 rounded-pill py-2 fw-bold shadow-sm" id="btnSubmitSale" disabled>
                <i class="bi bi-check2-circle me-2"></i> Registrar Venta
            </button>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('productSearch');
    const searchResults = document.getElementById('searchResults');
    const cartBody = document.getElementById('cartBody');
    const emptyCartRow = document.getElementById('emptyCartRow');
    const discountInput = document.getElementById('discount');
    const btnSubmit = document.getElementById('btnSubmitSale');

    let cart = [];
    let searchTimeout = null;

    // ── Product search with debounce ──
    searchInput.addEventListener('input', function () {
        clearTimeout(searchTimeout);
        const q = this.value.trim();
        if (q.length < 2) {
            searchResults.style.display = 'none';
            return;
        }

        searchTimeout = setTimeout(() => {
            fetch(`{{ route('api.products.search') }}?q=${encodeURIComponent(q)}`)
                .then(r => r.json())
                .then(products => {
                    searchResults.innerHTML = '';
                    if (products.length === 0) {
                        searchResults.innerHTML = '<div class="list-group-item text-muted small py-2">No se encontraron productos</div>';
                    } else {
                        products.forEach(p => {
                            const inCart = cart.find(c => c.product_id === p.id);
                            const disabled = inCart ? 'opacity-50' : '';
                            const label = inCart ? ' (ya en carrito)' : '';
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = `list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2 ${disabled}`;
                            btn.innerHTML = `
                                <div>
                                    <span class="fw-semibold">${escapeHtml(p.name)}</span>
                                    <small class="text-muted ms-2 font-monospace">${escapeHtml(p.sku || '')}</small>
                                    <small class="text-muted">${label}</small>
                                </div>
                                <div class="text-end">
                                    <span class="fw-bold text-primary">$${formatNumber(p.sale_price)}</span>
                                    <small class="text-muted d-block">Stock: ${p.stock}</small>
                                </div>
                            `;
                            if (!inCart) {
                                btn.addEventListener('click', () => addToCart(p));
                            }
                            searchResults.appendChild(btn);
                        });
                    }
                    searchResults.style.display = 'block';
                });
        }, 300);
    });

    // Close search results on click outside
    document.addEventListener('click', function (e) {
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.style.display = 'none';
        }
    });

    // ── Cart management ──
    function addToCart(product) {
        cart.push({
            product_id: product.id,
            name: product.name,
            sku: product.sku,
            stock: product.stock,
            quantity: 1,
            unit_price: parseFloat(product.sale_price),
        });
        searchInput.value = '';
        searchResults.style.display = 'none';
        renderCart();
    }

    function removeFromCart(index) {
        cart.splice(index, 1);
        renderCart();
    }

    function renderCart() {
        cartBody.innerHTML = '';

        if (cart.length === 0) {
            cartBody.appendChild(emptyCartRow);
            btnSubmit.disabled = true;
            updateTotals();
            return;
        }

        btnSubmit.disabled = false;

        cart.forEach((item, index) => {
            const lineSubtotal = item.quantity * item.unit_price;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <span class="fw-semibold">${escapeHtml(item.name)}</span>
                    <small class="text-muted d-block font-monospace">${escapeHtml(item.sku || '')}</small>
                    <input type="hidden" name="items[${index}][product_id]" value="${item.product_id}">
                    <input type="hidden" name="items[${index}][unit_price]" value="${item.unit_price}">
                </td>
                <td class="text-center">
                    <span class="badge ${item.stock <= 0 ? 'bg-danger' : item.stock <= 5 ? 'bg-warning text-dark' : 'bg-light text-dark border'} rounded-pill">${item.stock}</span>
                </td>
                <td class="text-center">
                    <input type="number" name="items[${index}][quantity]" value="${item.quantity}"
                           class="form-control form-control-sm text-center qty-input"
                           min="1" max="${item.stock}" data-index="${index}" style="width:80px; margin:0 auto;">
                </td>
                <td class="text-end">$${formatNumber(item.unit_price)}</td>
                <td class="text-end fw-semibold">$${formatNumber(lineSubtotal)}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger rounded-circle remove-btn" data-index="${index}">
                        <i class="bi bi-trash3"></i>
                    </button>
                </td>
            `;
            cartBody.appendChild(tr);
        });

        // Bind quantity change
        document.querySelectorAll('.qty-input').forEach(input => {
            input.addEventListener('change', function () {
                const idx = parseInt(this.dataset.index);
                let val = parseInt(this.value) || 1;
                if (val < 1) val = 1;
                if (val > cart[idx].stock) val = cart[idx].stock;
                this.value = val;
                cart[idx].quantity = val;
                renderCart();
            });
        });

        // Bind remove buttons
        document.querySelectorAll('.remove-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                removeFromCart(parseInt(this.dataset.index));
            });
        });

        updateTotals();
    }

    // ── Totals calculation ──
    function updateTotals() {
        let subtotal = 0;
        cart.forEach(item => {
            subtotal += item.quantity * item.unit_price;
        });

        const discount = parseFloat(discountInput.value) || 0;
        const total = Math.max(subtotal - discount, 0);

        document.getElementById('displaySubtotal').textContent = '$' + formatNumber(subtotal);
        document.getElementById('displayDiscount').textContent = '-$' + formatNumber(discount);
        document.getElementById('displayTotal').textContent = '$' + formatNumber(total);
    }

    discountInput.addEventListener('input', updateTotals);

    // ── Utilities ──
    function formatNumber(n) {
        return parseFloat(n).toLocaleString('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }
});
</script>
@endsection
