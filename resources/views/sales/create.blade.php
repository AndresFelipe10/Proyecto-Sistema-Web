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
                    <label class="form-label small fw-semibold text-muted d-flex justify-content-between align-items-center">
                        <span>Cliente</span>
                        <small class="text-secondary">Opcional</small>
                    </label>

                    {{-- Hidden input with authoritative customer_id --}}
                    <input type="hidden" id="customer_id" name="customer_id" 
                           value="{{ old('customer_id', $selectedCustomer?->id ?? '') }}">

                    {{-- Chip del cliente seleccionado --}}
                    <div id="customer_chip_container" class="mb-2 p-2 rounded-3 border bg-light d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center overflow-hidden me-2">
                            <i id="customer_chip_icon" class="bi {{ ($selectedCustomer || old('customer_id')) ? 'bi-person-check-fill text-success' : 'bi-person text-secondary' }} fs-5 me-2 flex-shrink-0"></i>
                            <div class="small text-truncate">
                                <span id="customer_chip_name" class="fw-bold d-block text-truncate">
                                    {{ $selectedCustomer ? $selectedCustomer->name : ($defaultCustomerName ?? config('sales.default_customer_name')) }}
                                </span>
                                <span id="customer_chip_doc" class="text-muted font-monospace small">
                                    {{ $selectedCustomer ? ($selectedCustomer->document ?? $selectedCustomer->identification_number) : ($defaultCustomerDocument ?? config('sales.default_customer_document')) }}
                                </span>
                            </div>
                        </div>
                        <button type="button" id="btn_remove_customer" class="btn btn-sm btn-outline-danger py-0 px-2 rounded-pill {{ ($selectedCustomer || old('customer_id')) ? '' : 'd-none' }}" title="Quitar cliente (Consumidor Final)">
                            <i class="bi bi-x"></i> Quitar
                        </button>
                    </div>

                    {{-- Campo de búsqueda y botón '+' --}}
                    <div class="position-relative">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" id="customer_search" class="form-control" 
                                   placeholder="Buscar cliente (Cédula/NIT o nombre)..." 
                                   autocomplete="off">
                            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#quickCustomerModal" title="Crear cliente rápido">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        </div>
                        {{-- Dropdown de resultados --}}
                        <div id="customer_search_results" class="list-group position-absolute shadow w-100 mt-1 d-none" style="z-index: 1060; max-height: 220px; overflow-y: auto;"></div>
                    </div>
                </div>

                {{-- Métodos de Pago y Pagos Mixtos --}}
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label small fw-semibold text-muted mb-0">
                            <i class="bi bi-wallet2 me-1"></i>Métodos de Pago
                        </label>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill py-0 px-2" id="btnAddPaymentLine" title="Agregar método de pago">
                            <i class="bi bi-plus-circle me-1"></i>Agregar método
                        </button>
                    </div>

                    {{-- Contenedor dinámico de líneas de pago --}}
                    <div id="payment_lines_container" class="d-flex flex-column gap-2 mb-2"></div>

                    {{-- Indicador dinámico de saldo / balance --}}
                    <div id="payment_balance_feedback" class="small p-2 rounded-3 text-center fw-semibold mb-2"></div>

                    {{-- Contenedor de inputs ocultos para submit --}}
                    <div id="hidden_payments_inputs"></div>
                    <input type="hidden" name="payment_method" id="hidden_payment_method" value="cash">

                    @error('payments')
                        <div class="alert alert-danger small py-1 px-2 mt-1 mb-0">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="discount_percentage" class="form-label small fw-semibold text-muted">Descuento (%)</label>
                    <div class="input-group">
                        <input type="number" id="discount_percentage" name="discount_percentage" class="form-control"
                               value="{{ old('discount_percentage', 0) }}" min="0" max="100" step="any">
                        <span class="input-group-text bg-light">%</span>
                    </div>
                    <div id="discountFeedback" class="form-text text-info mt-1" style="display:none;">
                        <i class="bi bi-info-circle me-1"></i>Equivale a <strong id="discountMoneyPreview">$0</strong> de descuento
                    </div>
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

{{-- Modal de creación rápida de cliente --}}
<div class="modal fade" id="quickCustomerModal" tabindex="-1" aria-labelledby="quickCustomerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold" id="quickCustomerModalLabel">
                    <i class="bi bi-person-plus text-primary me-2"></i>Crear Cliente Rápido
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <div id="quickCustomerErrors" class="alert alert-danger d-none small mb-3"></div>

                <div class="mb-3">
                    <label for="quick_customer_document" class="form-label small fw-semibold text-muted">Cédula o NIT <span class="text-danger">*</span></label>
                    <input type="text" id="quick_customer_document" class="form-control font-monospace" placeholder="Ej: 1144001234 o 900123456-1" required>
                    <div class="form-text small">Solo dígitos (5-15) y guión opcional con dígito de verificación.</div>
                </div>

                <div class="mb-3">
                    <label for="quick_customer_name" class="form-label small fw-semibold text-muted">Nombre o Razón Social <span class="text-danger">*</span></label>
                    <input type="text" id="quick_customer_name" class="form-control" placeholder="Nombre completo" required>
                </div>

                <div class="mb-3">
                    <label for="quick_customer_phone" class="form-label small fw-semibold text-muted">Teléfono <span class="text-muted">(opcional)</span></label>
                    <input type="text" id="quick_customer_phone" class="form-control" placeholder="Ej: 3151234567">
                </div>

                <div class="mb-3">
                    <label for="quick_customer_email" class="form-label small fw-semibold text-muted">Correo Electrónico <span class="text-muted">(opcional)</span></label>
                    <input type="email" id="quick_customer_email" class="form-control" placeholder="cliente@ejemplo.com">
                </div>
            </div>
            <div class="modal-footer border-top bg-light">
                <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="btn_save_quick_customer" class="btn btn-primary btn-sm rounded-pill px-4">
                    <i class="bi bi-save me-1"></i> Guardar Cliente
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('productSearch');
    const searchResults = document.getElementById('searchResults');
    const cartBody = document.getElementById('cartBody');
    const emptyCartRow = document.getElementById('emptyCartRow');
    const discountInput = document.getElementById('discount_percentage');
    const discountFeedback = document.getElementById('discountFeedback');
    const discountMoneyPreview = document.getElementById('discountMoneyPreview');
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

    // ── Totals calculation (percentage-based discount) ──
    function updateTotals() {
        let subtotal = 0;
        cart.forEach(item => {
            subtotal += item.quantity * item.unit_price;
        });

        const pct = parseFloat(discountInput.value) || 0;
        const discountMoney = Math.round(subtotal * (pct / 100));
        const total = Math.max(subtotal - discountMoney, 0);

        document.getElementById('displaySubtotal').textContent = '$' + formatNumber(subtotal);
        document.getElementById('displayDiscount').textContent = '-$' + formatNumber(discountMoney);
        document.getElementById('displayTotal').textContent = '$' + formatNumber(total);

        // Show/hide peso equivalent feedback
        if (pct > 0 && subtotal > 0) {
            discountMoneyPreview.textContent = '$' + formatNumber(discountMoney);
            discountFeedback.style.display = 'block';
        } else {
            discountFeedback.style.display = 'none';
        }

        // Sincronizar pago por defecto en efectivo y renderizar
        syncDefaultCashPayment(total);
        renderPaymentLines();
        updatePaymentsFeedback();
    }

    discountInput.addEventListener('input', updateTotals);

    // ── Estado y Manejo de Pagos Mixtos y Vueltos ──
    let payments = [
        { method: 'cash', amount: 0, reference: '', cash_received: null, change_given: 0 }
    ];
    let userModifiedPayments = false;

    function getCurrentTotal() {
        let subtotal = 0;
        cart.forEach(item => {
            subtotal += item.quantity * item.unit_price;
        });
        const pct = parseFloat(discountInput.value) || 0;
        const discountMoney = Math.round(subtotal * (pct / 100));
        return Math.max(subtotal - discountMoney, 0);
    }

    function syncDefaultCashPayment(total) {
        if (!userModifiedPayments && payments.length === 1 && payments[0].method === 'cash') {
            payments[0].amount = total;
            payments[0].cash_received = null;
            payments[0].change_given = 0;
        }
    }

    function getSmartShortcuts(amount) {
        const list = [{ label: 'Exacto', val: 'exact' }];
        if (amount <= 0) {
            [10000, 20000, 50000, 100000].forEach(v => list.push({ label: '$' + formatNumber(v), val: v }));
            return list;
        }
        const candidates = new Set();
        const next10k = Math.ceil(amount / 10000) * 10000;
        if (next10k > amount) candidates.add(next10k);
        const next20k = Math.ceil(amount / 20000) * 20000;
        if (next20k > amount) candidates.add(next20k);
        const next50k = Math.ceil(amount / 50000) * 50000;
        if (next50k > amount) candidates.add(next50k);
        const next100k = Math.ceil(amount / 100000) * 100000;
        if (next100k > amount) candidates.add(next100k);

        const sorted = Array.from(candidates).sort((a, b) => a - b).slice(0, 3);
        sorted.forEach(val => {
            list.push({ label: '$' + formatNumber(val), val: val });
        });
        return list;
    }

    function renderPaymentLines() {
        const container = document.getElementById('payment_lines_container');
        if (!container) return;
        container.innerHTML = '';
        const total = getCurrentTotal();
        const hasCashSelected = payments.some(item => item.method === 'cash');

        payments.forEach((p, idx) => {
            const card = document.createElement('div');
            card.className = 'card p-2 bg-light border-0 rounded-3 shadow-none mb-2';

            // Fila principal: Selector de método, input de monto y botón de eliminar
            const row = document.createElement('div');
            row.className = 'row g-2 align-items-center';

            // Columna selector de método
            const colMethod = document.createElement('div');
            colMethod.className = 'col-sm-5';
            const select = document.createElement('select');
            select.className = 'form-select form-select-sm';

            const options = [
                { val: 'cash', text: '💵 Efectivo' },
                { val: 'transfer', text: '📲 Transferencia / Nequi' },
                { val: 'card', text: '💳 Tarjeta' },
                { val: 'other', text: '✨ Otro' }
            ];

            options.forEach(opt => {
                const optEl = document.createElement('option');
                optEl.value = opt.val;
                optEl.textContent = opt.text;
                if (p.method === opt.val) {
                    optEl.selected = true;
                } else if (opt.val === 'cash' && hasCashSelected) {
                    optEl.disabled = true;
                }
                select.appendChild(optEl);
            });

            select.addEventListener('change', function() {
                userModifiedPayments = true;
                p.method = this.value;
                if (p.method === 'cash') {
                    p.cash_received = null;
                    p.change_given = 0;
                } else {
                    p.cash_received = null;
                    p.change_given = null;
                }
                renderPaymentLines();
                updatePaymentsFeedback();
            });
            colMethod.appendChild(select);
            row.appendChild(colMethod);

            // Columna de monto
            const colAmount = document.createElement('div');
            colAmount.className = 'col-sm-5';
            const inputGroup = document.createElement('div');
            inputGroup.className = 'input-group input-group-sm';
            const spanPrefix = document.createElement('span');
            spanPrefix.className = 'input-group-text';
            spanPrefix.textContent = '$';
            const inputAmount = document.createElement('input');
            inputAmount.type = 'number';
            inputAmount.className = 'form-control text-end fw-semibold font-monospace';
            inputAmount.min = '0';
            inputAmount.step = 'any';
            inputAmount.value = p.amount > 0 ? p.amount : (payments.length === 1 && total > 0 ? total : '');
            inputAmount.placeholder = '0';

            inputAmount.addEventListener('input', function() {
                userModifiedPayments = true;
                p.amount = parseFloat(this.value) || 0;
                if (p.method === 'cash') {
                    if (p.cash_received !== undefined && p.cash_received !== null && p.cash_received !== '') {
                        p.change_given = Math.max(0, p.cash_received - p.amount);
                    } else {
                        p.change_given = 0;
                    }
                    if (typeof updateChangeDisplay === 'function') {
                        updateChangeDisplay();
                    }
                }
                updatePaymentsFeedback();
            });
            inputGroup.appendChild(spanPrefix);
            inputGroup.appendChild(inputAmount);
            colAmount.appendChild(inputGroup);

            // Botón rápido para auto-ajustar al saldo restante cuando hay más de 1 método
            if (payments.length > 1) {
                const otherSum = payments.reduce((acc, curr, i) => i !== idx ? acc + (curr.amount || 0) : acc, 0);
                const remainingForThis = Math.max(0, Math.round((total - otherSum) * 100) / 100);
                if (remainingForThis !== p.amount) {
                    const btnAdjust = document.createElement('button');
                    btnAdjust.type = 'button';
                    btnAdjust.className = 'btn btn-link btn-xs p-0 text-decoration-none text-primary mt-1 d-block text-end w-100';
                    btnAdjust.style.fontSize = '0.72rem';
                    btnAdjust.innerHTML = `<i class="bi bi-magic me-1"></i>Ajustar al restante ($${formatNumber(remainingForThis)})`;
                    btnAdjust.title = 'Ajustar este método para completar el total de la venta';
                    btnAdjust.addEventListener('click', function() {
                        p.amount = remainingForThis;
                        inputAmount.value = p.amount;
                        if (p.method === 'cash') {
                            p.cash_received = null;
                            p.change_given = 0;
                        }
                        renderPaymentLines();
                        updatePaymentsFeedback();
                    });
                    colAmount.appendChild(btnAdjust);
                }
            }

            row.appendChild(colAmount);

            // Columna de eliminar línea (si hay más de 1)
            const colDelete = document.createElement('div');
            colDelete.className = 'col-sm-2 text-end';
            if (payments.length > 1) {
                const btnDel = document.createElement('button');
                btnDel.type = 'button';
                btnDel.className = 'btn btn-sm btn-outline-danger border-0';
                btnDel.title = 'Eliminar método';
                btnDel.innerHTML = '<i class="bi bi-trash3"></i>';
                btnDel.addEventListener('click', function() {
                    payments.splice(idx, 1);
                    userModifiedPayments = true;
                    renderPaymentLines();
                    updatePaymentsFeedback();
                });
                colDelete.appendChild(btnDel);
            }
            row.appendChild(colDelete);
            card.appendChild(row);

            // Declarar updateChangeDisplay en alcance común para la tarjeta de efectivo
            let updateChangeDisplay = null;

            // Sección específica para Efectivo (Paga con, Atajos y Vueltos)
            if (p.method === 'cash') {
                const cashSection = document.createElement('div');
                cashSection.className = 'mt-2 pt-2 border-top';

                const receivedRow = document.createElement('div');
                receivedRow.className = 'row g-2 align-items-center mb-1';
                const labelCol = document.createElement('div');
                labelCol.className = 'col-auto';
                labelCol.innerHTML = '<span class="small text-muted fw-semibold">Paga con / Recibido:</span>';
                receivedRow.appendChild(labelCol);

                const recInputCol = document.createElement('div');
                recInputCol.className = 'col';
                const recGroup = document.createElement('div');
                recGroup.className = 'input-group input-group-sm';
                const recPrefix = document.createElement('span');
                recPrefix.className = 'input-group-text';
                recPrefix.textContent = '$';
                const recInput = document.createElement('input');
                recInput.type = 'number';
                recInput.className = 'form-control text-end font-monospace fw-semibold';
                recInput.min = '0';
                recInput.step = 'any';
                recInput.value = (p.cash_received !== undefined && p.cash_received !== null) ? p.cash_received : '';
                recInput.placeholder = p.amount > 0 ? ('$' + formatNumber(p.amount) + ' (o atajo)') : '0';

                recGroup.appendChild(recPrefix);
                recGroup.appendChild(recInput);
                recInputCol.appendChild(recGroup);
                receivedRow.appendChild(recInputCol);
                cashSection.appendChild(receivedRow);

                // Botones de atajos rápidos inteligentes basados en el monto a pagar
                const shortcutsRow = document.createElement('div');
                shortcutsRow.className = 'd-flex flex-wrap gap-1 mb-2';

                function renderShortcuts() {
                    shortcutsRow.innerHTML = '';
                    const shortcuts = getSmartShortcuts(p.amount);
                    shortcuts.forEach(sc => {
                        const btnSc = document.createElement('button');
                        btnSc.type = 'button';
                        btnSc.className = 'btn btn-outline-secondary btn-sm py-0 px-2 rounded-pill';
                        btnSc.style.fontSize = '0.75rem';
                        btnSc.textContent = sc.label;
                        btnSc.addEventListener('click', function() {
                            const targetVal = sc.val === 'exact' ? p.amount : sc.val;
                            recInput.value = targetVal;
                            p.cash_received = targetVal;
                            p.change_given = Math.max(0, targetVal - p.amount);
                            updateChangeDisplay();
                            updatePaymentsFeedback();
                        });
                        shortcutsRow.appendChild(btnSc);
                    });
                }
                renderShortcuts();
                cashSection.appendChild(shortcutsRow);

                // Fila de Vuelto / Cambio o estado de entrega de efectivo
                const changeRow = document.createElement('div');
                changeRow.className = 'd-flex justify-content-between align-items-center mt-1 flex-wrap gap-1';

                updateChangeDisplay = function() {
                    changeRow.innerHTML = '';
                    if (p.amount <= 0) {
                        return;
                    }

                    if (p.cash_received === null || p.cash_received === undefined) {
                        const note = document.createElement('span');
                        note.className = 'small text-muted font-monospace';
                        note.innerHTML = '<i class="bi bi-info-circle me-1"></i>Exacto o ingrese con cuánto paga el cliente';
                        changeRow.appendChild(note);
                        return;
                    }

                    const diff = p.cash_received - p.amount;

                    if (diff >= 0) {
                        const label = document.createElement('span');
                        label.className = 'small text-muted';
                        label.textContent = 'Vuelto / Cambio:';
                        changeRow.appendChild(label);

                        const badge = document.createElement('span');
                        badge.className = 'badge bg-success font-monospace cash-change-badge';
                        badge.textContent = 'Vuelto: $' + formatNumber(diff);
                        changeRow.appendChild(badge);
                    } else {
                        const shortAmt = Math.abs(diff);
                        const alertWrapper = document.createElement('div');
                        alertWrapper.className = 'd-flex align-items-center justify-content-between w-100 flex-wrap gap-1';

                        const label = document.createElement('span');
                        label.className = 'badge bg-danger-subtle text-danger border border-danger-subtle font-monospace text-wrap text-start py-1';
                        label.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-1"></i>Efectivo menor al asignado (faltan $${formatNumber(shortAmt)})`;
                        alertWrapper.appendChild(label);

                        if (p.cash_received > 0) {
                            const btnSetAmount = document.createElement('button');
                            btnSetAmount.type = 'button';
                            btnSetAmount.className = 'btn btn-link btn-xs p-0 text-decoration-none text-primary fw-semibold';
                            btnSetAmount.style.fontSize = '0.75rem';
                            btnSetAmount.innerHTML = `<i class="bi bi-arrow-down-left me-1"></i>Cobrar solo $${formatNumber(p.cash_received)} en efectivo`;
                            btnSetAmount.title = `Fijar $${formatNumber(p.cash_received)} como el monto a cobrar en efectivo`;
                            btnSetAmount.addEventListener('click', function() {
                                p.amount = p.cash_received;
                                p.change_given = 0;
                                renderPaymentLines();
                                updatePaymentsFeedback();
                            });
                            alertWrapper.appendChild(btnSetAmount);
                        }

                        changeRow.appendChild(alertWrapper);
                    }
                };
                updateChangeDisplay();

                recInput.addEventListener('input', function() {
                    const rawVal = this.value.trim();
                    if (rawVal === '') {
                        p.cash_received = null;
                        p.change_given = 0;
                    } else {
                        p.cash_received = parseFloat(rawVal) || 0;
                        p.change_given = Math.max(0, p.cash_received - p.amount);
                    }
                    updateChangeDisplay();
                    updatePaymentsFeedback();
                });

                cashSection.appendChild(changeRow);
                card.appendChild(cashSection);
            } else {
                // Input de referencia para transferencias/tarjetas/otros
                const refSection = document.createElement('div');
                refSection.className = 'mt-2 pt-2 border-top';
                const refInput = document.createElement('input');
                refInput.type = 'text';
                refInput.className = 'form-control form-control-sm';
                refInput.maxLength = 60;
                refInput.placeholder = 'Referencia / # aprobación / Nequi (opcional)';
                refInput.value = p.reference || '';
                refInput.addEventListener('input', function() {
                    p.reference = this.value;
                    syncHiddenPaymentInputs();
                });
                refSection.appendChild(refInput);
                card.appendChild(refSection);
            }

            container.appendChild(card);
        });

        // Deshabilitar botón de agregar si ya hay 5 líneas
        const btnAdd = document.getElementById('btnAddPaymentLine');
        if (btnAdd) {
            btnAdd.disabled = payments.length >= 5;
        }
    }

    function updatePaymentsFeedback() {
        const feedback = document.getElementById('payment_balance_feedback');
        if (!feedback) return;
        const total = getCurrentTotal();
        let sum = 0;
        let cashValid = true;

        payments.forEach(p => {
            sum += (p.amount || 0);
            if (p.method === 'cash') {
                const rec = p.cash_received !== undefined && p.cash_received !== null ? p.cash_received : p.amount;
                if (rec < p.amount) {
                    cashValid = false;
                }
            }
        });

        const diff = Math.round(total * 100) - Math.round(sum * 100);

        if (total === 0) {
            feedback.className = 'small p-2 rounded-3 text-center fw-semibold bg-light text-muted';
            feedback.textContent = 'Venta con 100% de descuento ($0)';
        } else if (diff > 0) {
            const missing = diff / 100;
            feedback.className = 'small p-2 rounded-3 fw-semibold bg-danger-subtle text-danger border border-danger-subtle d-flex align-items-center justify-content-between flex-wrap gap-2';
            feedback.innerHTML = `
                <span><i class="bi bi-exclamation-triangle-fill me-1"></i>Falta asignar a los métodos: <strong>$${formatNumber(missing)}</strong></span>
            `;
            if (payments.length > 0) {
                const btnBalance = document.createElement('button');
                btnBalance.type = 'button';
                btnBalance.className = 'btn btn-outline-danger btn-sm py-0 px-2 rounded-pill';
                btnBalance.style.fontSize = '0.75rem';
                btnBalance.textContent = '+ Asignar al último método';
                btnBalance.addEventListener('click', function() {
                    const lastP = payments[payments.length - 1];
                    lastP.amount = Math.round(((lastP.amount || 0) + missing) * 100) / 100;
                    if (lastP.method === 'cash') {
                        lastP.cash_received = Math.max(lastP.cash_received || 0, lastP.amount);
                        lastP.change_given = Math.max(0, lastP.cash_received - lastP.amount);
                    }
                    renderPaymentLines();
                    updatePaymentsFeedback();
                });
                feedback.appendChild(btnBalance);
            }
        } else if (diff < 0) {
            const excess = Math.abs(diff) / 100;
            feedback.className = 'small p-2 rounded-3 fw-semibold bg-warning-subtle text-warning-emphasis border border-warning-subtle d-flex align-items-center justify-content-between flex-wrap gap-2';
            feedback.innerHTML = `
                <span><i class="bi bi-exclamation-circle-fill me-1"></i>Suma de métodos ($${formatNumber(sum)}) supera el total ($${formatNumber(total)}) por <strong>$${formatNumber(excess)}</strong></span>
            `;
            if (payments.length > 0) {
                const btnBalance = document.createElement('button');
                btnBalance.type = 'button';
                btnBalance.className = 'btn btn-outline-warning btn-sm py-0 px-2 rounded-pill text-dark';
                btnBalance.style.fontSize = '0.75rem';
                btnBalance.textContent = 'Ajustar al total';
                btnBalance.addEventListener('click', function() {
                    let remainingExcess = excess;
                    for (let i = payments.length - 1; i >= 0; i--) {
                        if (payments[i].amount >= remainingExcess) {
                            payments[i].amount = Math.round((payments[i].amount - remainingExcess) * 100) / 100;
                            if (payments[i].method === 'cash') {
                                payments[i].change_given = Math.max(0, (payments[i].cash_received || payments[i].amount) - payments[i].amount);
                            }
                            remainingExcess = 0;
                            break;
                        }
                    }
                    renderPaymentLines();
                    updatePaymentsFeedback();
                });
                feedback.appendChild(btnBalance);
            }
        } else {
            if (cashValid) {
                feedback.className = 'small p-2 rounded-3 text-center fw-semibold bg-success-subtle text-success border border-success-subtle';
                feedback.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i>Monto exacto cubierto ($${formatNumber(total)})`;
            } else {
                feedback.className = 'small p-2 rounded-3 text-center fw-semibold bg-danger-subtle text-danger border border-danger-subtle';
                feedback.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-1"></i>Monto total cubierto, pero el efectivo entregado es menor al asignado a esa línea`;
            }
        }

        // Estado del botón submit
        const isCartValid = cart.length > 0;
        const isAmountCovered = total === 0 || diff === 0;
        const allAmountsPositive = total === 0 || payments.every(p => p.amount > 0);

        if (isCartValid && isAmountCovered && allAmountsPositive && cashValid) {
            btnSubmit.disabled = false;
        } else {
            btnSubmit.disabled = true;
        }

        syncHiddenPaymentInputs();
    }

    function syncHiddenPaymentInputs() {
        const hiddenContainer = document.getElementById('hidden_payments_inputs');
        if (!hiddenContainer) return;
        hiddenContainer.innerHTML = '';

        const hiddenPaymentMethod = document.getElementById('hidden_payment_method');
        if (hiddenPaymentMethod) {
            hiddenPaymentMethod.value = payments.length === 1 ? payments[0].method : 'mixed';
        }

        payments.forEach((p, idx) => {
            const isCash = p.method === 'cash';
            const hasExplicitRec = p.cash_received !== undefined && p.cash_received !== null && p.cash_received !== '';
            const cashRec = isCash ? (hasExplicitRec ? p.cash_received : p.amount) : '';
            const change = isCash ? Math.max(0, (hasExplicitRec ? p.cash_received : p.amount) - p.amount) : '';

            hiddenContainer.innerHTML += `
                <input type="hidden" name="payments[${idx}][method]" value="${escapeHtml(p.method)}">
                <input type="hidden" name="payments[${idx}][amount]" value="${p.amount}">
                <input type="hidden" name="payments[${idx}][reference]" value="${escapeHtml(p.reference || '')}">
                <input type="hidden" name="payments[${idx}][cash_received]" value="${cashRec}">
                <input type="hidden" name="payments[${idx}][change_given]" value="${change}">
            `;
        });
    }

    // Botón agregar método de pago
    const btnAddLine = document.getElementById('btnAddPaymentLine');
    if (btnAddLine) {
        btnAddLine.addEventListener('click', function() {
            if (payments.length >= 5) return;
            userModifiedPayments = true;

            const hasCash = payments.some(p => p.method === 'cash');
            const newMethod = hasCash ? 'transfer' : 'cash';

            let sum = 0;
            payments.forEach(p => sum += (p.amount || 0));
            const total = getCurrentTotal();
            const remaining = Math.max(0, total - sum);

            payments.push({
                method: newMethod,
                amount: remaining,
                reference: '',
                cash_received: null,
                change_given: 0
            });

            renderPaymentLines();
            updatePaymentsFeedback();
        });
    }

    const saleForm = document.getElementById('saleForm');
    if (saleForm) {
        saleForm.addEventListener('submit', function() {
            syncHiddenPaymentInputs();
        });
    }

    // ── Búsqueda y Selección de Cliente (POS) ──
    const customerIdInput = document.getElementById('customer_id');
    const customerChipName = document.getElementById('customer_chip_name');
    const customerChipDoc = document.getElementById('customer_chip_doc');
    const customerChipIcon = document.getElementById('customer_chip_icon');
    const btnRemoveCustomer = document.getElementById('btn_remove_customer');
    const customerSearchInput = document.getElementById('customer_search');
    const customerSearchResults = document.getElementById('customer_search_results');

    const defaultCustomerName = @json($defaultCustomerName ?? config('sales.default_customer_name'));
    const defaultCustomerDoc = @json($defaultCustomerDocument ?? config('sales.default_customer_document'));

    function selectCustomer(id, name, doc) {
        if (id) {
            customerIdInput.value = id;
            customerChipName.textContent = name;
            customerChipDoc.textContent = doc ? `Doc: ${doc}` : '';
            customerChipIcon.className = 'bi bi-person-check-fill text-success fs-5 me-2 flex-shrink-0';
            btnRemoveCustomer.classList.remove('d-none');
        } else {
            customerIdInput.value = '';
            customerChipName.textContent = defaultCustomerName;
            customerChipDoc.textContent = defaultCustomerDoc;
            customerChipIcon.className = 'bi bi-person text-secondary fs-5 me-2 flex-shrink-0';
            btnRemoveCustomer.classList.add('d-none');
        }
        customerSearchInput.value = '';
        customerSearchResults.classList.add('d-none');
        customerSearchResults.innerHTML = '';
    }

    btnRemoveCustomer.addEventListener('click', function() {
        selectCustomer('', '', '');
    });

    let customerSearchTimeout = null;
    customerSearchInput.addEventListener('input', function() {
        const query = this.value.trim();
        clearTimeout(customerSearchTimeout);

        if (query.length < 3) {
            customerSearchResults.classList.add('d-none');
            customerSearchResults.innerHTML = '';
            return;
        }

        customerSearchTimeout = setTimeout(() => {
            fetch(`{{ route('customers.search') }}?q=${encodeURIComponent(query)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                customerSearchResults.innerHTML = '';
                if (!Array.isArray(data) || data.length === 0) {
                    const emptyItem = document.createElement('div');
                    emptyItem.className = 'list-group-item text-muted small py-2';
                    emptyItem.textContent = 'No se encontraron clientes con ese documento o nombre';
                    customerSearchResults.appendChild(emptyItem);
                } else {
                    data.forEach(cust => {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2';

                        const infoDiv = document.createElement('div');
                        infoDiv.className = 'text-truncate me-2 text-start';

                        const nameSpan = document.createElement('span');
                        nameSpan.className = 'fw-semibold d-block text-truncate';
                        nameSpan.textContent = cust.name;

                        const docSpan = document.createElement('small');
                        docSpan.className = 'text-muted font-monospace';
                        docSpan.textContent = cust.document ? `Doc: ${cust.document}` : '';

                        infoDiv.appendChild(nameSpan);
                        infoDiv.appendChild(docSpan);

                        btn.appendChild(infoDiv);

                        if (cust.phone) {
                            const phoneSpan = document.createElement('small');
                            phoneSpan.className = 'badge bg-light text-secondary border';
                            phoneSpan.textContent = cust.phone;
                            btn.appendChild(phoneSpan);
                        }

                        btn.addEventListener('click', function() {
                            selectCustomer(cust.id, cust.name, cust.document);
                        });

                        customerSearchResults.appendChild(btn);
                    });
                }
                customerSearchResults.classList.remove('d-none');
            })
            .catch(() => {
                customerSearchResults.classList.add('d-none');
            });
        }, 280);
    });

    // Cerrar resultados al hacer click afuera
    document.addEventListener('click', function(e) {
        if (!customerSearchInput.contains(e.target) && !customerSearchResults.contains(e.target)) {
            customerSearchResults.classList.add('d-none');
        }
    });

    // Modal de creación rápida de cliente
    const btnSaveQuickCustomer = document.getElementById('btn_save_quick_customer');
    const quickCustomerErrors = document.getElementById('quickCustomerErrors');
    const quickCustomerModalEl = document.getElementById('quickCustomerModal');

    btnSaveQuickCustomer.addEventListener('click', function() {
        quickCustomerErrors.classList.add('d-none');
        quickCustomerErrors.innerHTML = '';

        const docVal = document.getElementById('quick_customer_document').value.trim();
        const nameVal = document.getElementById('quick_customer_name').value.trim();
        const phoneVal = document.getElementById('quick_customer_phone').value.trim();
        const emailVal = document.getElementById('quick_customer_email').value.trim();

        const csrfToken = document.querySelector('input[name="_token"]')?.value;

        btnSaveQuickCustomer.disabled = true;

        fetch('{{ route("customers.store") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                document: docVal,
                name: nameVal,
                phone: phoneVal,
                email: emailVal,
                is_active: true
            })
        })
        .then(async response => {
            const data = await response.json();
            btnSaveQuickCustomer.disabled = false;

            if (!response.ok) {
                const ul = document.createElement('ul');
                ul.className = 'mb-0 ps-3';

                if (data.errors) {
                    Object.values(data.errors).forEach(errArr => {
                        errArr.forEach(errText => {
                            const li = document.createElement('li');
                            li.textContent = errText;
                            ul.appendChild(li);
                        });
                    });
                } else if (data.message) {
                    const li = document.createElement('li');
                    li.textContent = data.message;
                    ul.appendChild(li);
                }

                quickCustomerErrors.appendChild(ul);
                quickCustomerErrors.classList.remove('d-none');
                return;
            }

            // Éxito: seleccionar el cliente creado
            selectCustomer(data.id, data.name, data.document);

            // Limpiar inputs del modal
            document.getElementById('quick_customer_document').value = '';
            document.getElementById('quick_customer_name').value = '';
            document.getElementById('quick_customer_phone').value = '';
            document.getElementById('quick_customer_email').value = '';

            const modalInstance = bootstrap.Modal.getInstance(quickCustomerModalEl);
            if (modalInstance) {
                modalInstance.hide();
            }
        })
        .catch(err => {
            btnSaveQuickCustomer.disabled = false;
            const p = document.createElement('p');
            p.className = 'mb-0';
            p.textContent = 'Ocurrió un error al procesar la solicitud. Intente nuevamente.';
            quickCustomerErrors.appendChild(p);
            quickCustomerErrors.classList.remove('d-none');
        });
    });

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
