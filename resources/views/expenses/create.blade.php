@extends('layouts.app')

@section('title', 'Registrar Gasto o Factura')

@section('content')
<div class="container-fluid px-4 py-3" style="max-width: 900px;">
    {{-- Navegación / Breadcrumb --}}
    <div class="d-flex align-items-center gap-2 mb-3">
        <a href="{{ route('expenses.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Volver a Gastos
        </a>
        <span class="text-muted">/</span>
        <span class="text-dark small fw-semibold">Nuevo Gasto o Factura</span>
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white border-bottom py-3">
            <h5 class="fw-bold mb-0">Registrar Nuevo Gasto / Factura de Compra</h5>
            <small class="text-muted">Registra egresos de operación o facturas de proveedores para <strong>{{ $currentBusiness->name }}</strong>.</small>
        </div>
        <div class="card-body p-4">
            {{-- Recordatorio contable --}}
            <div class="alert alert-light border border-secondary-subtle d-flex align-items-center gap-2 py-2 px-3 mb-4 rounded-3 small">
                <i class="bi bi-info-circle text-primary fs-5"></i>
                <span><strong>Aviso:</strong> Si el gasto corresponde a compra de mercancía, este registro es exclusivamente financiero. El ingreso físico de existencias se realiza mediante una entrada en el módulo de <strong>Inventario</strong>.</span>
            </div>

            @if($errors->any())
                <div class="alert alert-danger rounded-3 mb-4">
                    <ul class="mb-0 small ps-3">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('expenses.store') }}" enctype="multipart/form-data">
                @csrf

                <div class="row g-3">
                    {{-- Proveedor --}}
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold">Proveedor (Opcional)</label>
                        <select name="supplier_id" class="form-select @error('supplier_id') is-invalid @enderror">
                            <option value="">Sin proveedor / Gasto general</option>
                            @foreach($suppliers as $sup)
                                <option value="{{ $sup->id }}" @selected(old('supplier_id') == $sup->id)>{{ $sup->name }} @if($sup->nit)({{ $sup->nit }})@endif</option>
                            @endforeach
                        </select>
                        @error('supplier_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- N° de Factura / Cuenta --}}
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold">N° de Factura / Referencia (Opcional)</label>
                        <input type="text" name="invoice_number" class="form-control @error('invoice_number') is-invalid @enderror" placeholder="Ej. FAC-10492 o REF-5544" value="{{ old('invoice_number') }}" maxlength="50">
                        @error('invoice_number')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Categoría --}}
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold">Categoría <span class="text-danger">*</span></label>
                        <select name="category" class="form-select @error('category') is-invalid @enderror" required>
                            <option value="">Selecciona categoría...</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->value }}" @selected(old('category') === $cat->value)>{{ $cat->label() }}</option>
                            @endforeach
                        </select>
                        @error('category')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Monto --}}
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold">Monto Total ($ COP) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" step="0.01" min="0.01" name="amount" class="form-control @error('amount') is-invalid @enderror" placeholder="0" value="{{ old('amount') }}" required>
                        </div>
                        @error('amount')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Fecha de Emisión --}}
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold">Fecha de Emisión <span class="text-danger">*</span></label>
                        <input type="date" name="issue_date" id="issue_date" class="form-control @error('issue_date') is-invalid @enderror" value="{{ old('issue_date', date('Y-m-d')) }}" required>
                        @error('issue_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Fecha de Vencimiento --}}
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold">Fecha de Vencimiento (Opcional)</label>
                        <input type="date" name="due_date" id="due_date" class="form-control @error('due_date') is-invalid @enderror" value="{{ old('due_date') }}">
                        <small class="text-muted">Si se deja vacío, la factura no computará fecha límite de vencimiento.</small>
                        @error('due_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Descripción / Notas --}}
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Descripción o Concepto (Opcional)</label>
                        <input type="text" name="description" class="form-control @error('description') is-invalid @enderror" placeholder="Ej. Pago de arriendo local comercial mes en curso..." value="{{ old('description') }}" maxlength="255">
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Estado de Pago --}}
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Estado de Pago <span class="text-danger">*</span></label>
                        <div class="d-flex gap-4 p-2 bg-light rounded-3 border">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="status" id="status_pending" value="pending" @checked(old('status', 'pending') === 'pending') onchange="togglePaymentFields()">
                                <label class="form-check-label fw-semibold" for="status_pending">
                                    <i class="bi bi-clock text-warning me-1"></i> Pendiente / Por Pagar
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="status" id="status_paid" value="paid" @checked(old('status') === 'paid') onchange="togglePaymentFields()">
                                <label class="form-check-label fw-semibold" for="status_paid">
                                    <i class="bi bi-check-circle text-success me-1"></i> Ya Pagada / Liquidada
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- Campos condicionales de pago --}}
                    <div id="paymentFields" class="col-12 p-3 bg-light rounded-3 border @if(old('status', 'pending') !== 'paid') d-none @endif">
                        <h6 class="fw-bold small text-uppercase text-secondary mb-3">Detalle del Pago Realizado</h6>
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-semibold">Fecha en que se pagó <span class="text-danger">*</span></label>
                                <input type="date" name="paid_at" id="paid_at" class="form-control @error('paid_at') is-invalid @enderror" value="{{ old('paid_at', date('Y-m-d')) }}">
                                @error('paid_at')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-semibold">Método de Pago <span class="text-danger">*</span></label>
                                <select name="payment_method" id="payment_method" class="form-select @error('payment_method') is-invalid @enderror">
                                    <option value="">Selecciona método de pago...</option>
                                    @foreach($paymentMethods as $pm)
                                        <option value="{{ $pm->value }}" @selected(old('payment_method') === $pm->value)>{{ $pm->label() }}</option>
                                    @endforeach
                                </select>
                                @error('payment_method')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Comprobante Adjunto --}}
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Comprobante o Factura Adjunta (Opcional)</label>
                        <input type="file" name="attachment" class="form-control @error('attachment') is-invalid @enderror" accept=".pdf,.png,.jpg,.jpeg">
                        <small class="text-muted">Formatos admitidos: PDF, JPG, PNG (Máximo 3 MB). Se almacena de forma privada y segura.</small>
                        @error('attachment')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="{{ route('expenses.index') }}" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-save me-1"></i> Guardar Registro
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function togglePaymentFields() {
    var isPaid = document.getElementById('status_paid').checked;
    var fields = document.getElementById('paymentFields');
    var paidAt = document.getElementById('paid_at');
    var paymentMethod = document.getElementById('payment_method');

    if (isPaid) {
        fields.classList.remove('d-none');
        paidAt.setAttribute('required', 'required');
        paymentMethod.setAttribute('required', 'required');
    } else {
        fields.classList.add('d-none');
        paidAt.removeAttribute('required');
        paymentMethod.removeAttribute('required');
    }
}
</script>
@endsection
