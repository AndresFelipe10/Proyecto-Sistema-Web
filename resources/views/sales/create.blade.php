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
    }

    discountInput.addEventListener('input', updateTotals);

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
