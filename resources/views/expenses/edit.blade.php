@extends('layouts.app')

@section('title', 'Editar Gasto o Factura')

@section('content')
<div class="container-fluid px-4 py-3" style="max-width: 900px;">
    {{-- Navegación / Breadcrumb --}}
    <div class="d-flex align-items-center gap-2 mb-3">
        <a href="{{ route('expenses.show', $expense) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Volver al Detalle
        </a>
        <span class="text-muted">/</span>
        <span class="text-dark small fw-semibold">Editar Gasto</span>
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white border-bottom py-3">
            <h5 class="fw-bold mb-0">Editar Gasto / Factura de Compra</h5>
            <small class="text-muted">Modifica los datos del registro en <strong>{{ $currentBusiness->name }}</strong>.</small>
        </div>
        <div class="card-body p-4">
            @if($errors->any())
                <div class="alert alert-danger rounded-3 mb-4">
                    <ul class="mb-0 small ps-3">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('expenses.update', $expense) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="row g-3">
                    {{-- Proveedor --}}
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold">Proveedor (Opcional)</label>
                        <select name="supplier_id" class="form-select @error('supplier_id') is-invalid @enderror">
                            <option value="">Sin proveedor / Gasto general</option>
                            @foreach($suppliers as $sup)
                                <option value="{{ $sup->id }}" @selected(old('supplier_id', $expense->supplier_id) == $sup->id)>{{ $sup->name }} @if($sup->nit)({{ $sup->nit }})@endif</option>
                            @endforeach
                        </select>
                        @error('supplier_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- N° de Factura / Cuenta --}}
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold">N° de Factura / Referencia (Opcional)</label>
                        <input type="text" name="invoice_number" class="form-control @error('invoice_number') is-invalid @enderror" value="{{ old('invoice_number', $expense->invoice_number) }}" maxlength="50">
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
                                <option value="{{ $cat->value }}" @selected(old('category', $expense->category?->value ?? $expense->category) === $cat->value)>{{ $cat->label() }}</option>
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
                            <input type="number" step="0.01" min="0.01" name="amount" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount', $expense->amount) }}" required>
                        </div>
                        @error('amount')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Fecha de Emisión --}}
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold">Fecha de Emisión <span class="text-danger">*</span></label>
                        <input type="date" name="issue_date" id="issue_date" class="form-control @error('issue_date') is-invalid @enderror" value="{{ old('issue_date', $expense->issue_date->format('Y-m-d')) }}" required>
                        @error('issue_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Fecha de Vencimiento --}}
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold">Fecha de Vencimiento (Opcional)</label>
                        <input type="date" name="due_date" id="due_date" class="form-control @error('due_date') is-invalid @enderror" value="{{ old('due_date', $expense->due_date?->format('Y-m-d')) }}">
                        @error('due_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Descripción / Notas --}}
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Descripción o Concepto (Opcional)</label>
                        <input type="text" name="description" class="form-control @error('description') is-invalid @enderror" value="{{ old('description', $expense->description) }}" maxlength="255">
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Estado de Pago --}}
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Estado de Pago <span class="text-danger">*</span></label>
                        <div class="d-flex gap-4 p-2 bg-light rounded-3 border">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="status" id="status_pending" value="pending" @checked(old('status', $expense->status) === 'pending') onchange="togglePaymentFields()">
                                <label class="form-check-label fw-semibold" for="status_pending">
                                    <i class="bi bi-clock text-warning me-1"></i> Pendiente / Por Pagar
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="status" id="status_paid" value="paid" @checked(old('status', $expense->status) === 'paid') onchange="togglePaymentFields()">
                                <label class="form-check-label fw-semibold" for="status_paid">
                                    <i class="bi bi-check-circle text-success me-1"></i> Ya Pagada / Liquidada
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- Campos condicionales de pago --}}
                    <div id="paymentFields" class="col-12 p-3 bg-light rounded-3 border @if(old('status', $expense->status) !== 'paid') d-none @endif">
                        <h6 class="fw-bold small text-uppercase text-secondary mb-3">Detalle del Pago Realizado</h6>
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-semibold">Fecha en que se pagó <span class="text-danger">*</span></label>
                                <input type="date" name="paid_at" id="paid_at" class="form-control @error('paid_at') is-invalid @enderror" value="{{ old('paid_at', $expense->paid_at?->format('Y-m-d') ?? date('Y-m-d')) }}">
                                @error('paid_at')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-semibold">Método de Pago <span class="text-danger">*</span></label>
                                <select name="payment_method" id="payment_method" class="form-select @error('payment_method') is-invalid @enderror">
                                    <option value="">Selecciona método de pago...</option>
                                    @foreach($paymentMethods as $pm)
                                        <option value="{{ $pm->value }}" @selected(old('payment_method', $expense->payment_method?->value ?? $expense->payment_method) === $pm->value)>{{ $pm->label() }}</option>
                                    @endforeach
                                </select>
                                @error('payment_method')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Comprobante Adjunto Actual y Reemplazo --}}
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Comprobante o Factura Adjunta</label>
                        @if($expense->attachment_path)
                            <div class="d-flex align-items-center justify-content-between p-2 mb-2 bg-light border rounded-3">
                                <div class="d-flex align-items-center gap-2 small">
                                    <i class="bi bi-file-earmark-check text-success fs-5"></i>
                                    <span class="fw-semibold">{{ $expense->attachment_original_name ?? 'Comprobante adjunto' }}</span>
                                    <a href="{{ route('expenses.attachment', $expense) }}" target="_blank" class="btn btn-sm btn-link py-0">Descargar</a>
                                </div>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" name="remove_attachment" value="1" id="remove_attachment">
                                    <label class="form-check-label small text-danger" for="remove_attachment">Eliminar adjunto</label>
                                </div>
                            </div>
                        @endif
                        <input type="file" name="attachment" class="form-control @error('attachment') is-invalid @enderror" accept=".pdf,.png,.jpg,.jpeg">
                        <small class="text-muted">Si subes un nuevo archivo, reemplazará de forma segura el adjunto anterior. Formatos: PDF, JPG, PNG (Máx 3 MB).</small>
                        @error('attachment')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="{{ route('expenses.show', $expense) }}" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-save me-1"></i> Guardar Cambios
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
