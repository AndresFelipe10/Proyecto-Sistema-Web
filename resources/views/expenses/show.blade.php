@extends('layouts.app')

@section('title', 'Detalle de Gasto ' . ($expense->invoice_number ?? '#' . $expense->id))

@section('content')
<div class="container-fluid px-4 py-3" style="max-width: 900px;">
    {{-- Breadcrumb & Actions Header --}}
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-4">
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('expenses.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Volver a Gastos
            </a>
            <span class="text-muted">/</span>
            <span class="text-dark small fw-semibold">Detalle de Gasto</span>
        </div>
        <div class="d-flex gap-2">
            @if($expense->status === 'pending')
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#payModal">
                    <i class="bi bi-check-circle me-1"></i> Marcar como Pagada
                </button>
            @endif
            <a href="{{ route('expenses.edit', $expense) }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-pencil me-1"></i> Editar
            </a>
            <form action="{{ route('expenses.destroy', $expense) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Seguro que deseas eliminar este gasto?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-trash me-1"></i> Eliminar
                </button>
            </form>
        </div>
    </div>

    {{-- Tarjeta Principal --}}
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3 border-bottom pb-4 mb-4">
                <div>
                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle mb-2">
                        {{ $expense->category_label }}
                    </span>
                    <h3 class="fw-bold mb-1">
                        @if($expense->invoice_number)
                            Factura {{ $expense->invoice_number }}
                        @else
                            Gasto Operativo #{{ $expense->id }}
                        @endif
                    </h3>
                    <p class="text-muted small mb-0">Registrado por <strong>{{ $expense->creator?->name ?? 'Usuario del sistema' }}</strong> el {{ $expense->created_at->format('d/m/Y H:i') }}.</p>
                </div>
                <div class="text-md-end">
                    <div class="fs-2 fw-bold text-dark mb-1">
                        ${{ number_format($expense->amount, 0, ',', '.') }}
                    </div>
                    <div>
                        @if($expense->status === 'paid')
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fs-6">
                                <i class="bi bi-check-circle me-1"></i> Pagada
                            </span>
                        @elseif($expense->is_overdue)
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 fs-6">
                                <i class="bi bi-exclamation-triangle me-1"></i> Vencida
                            </span>
                        @else
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2 fs-6">
                                <i class="bi bi-clock me-1"></i> Pendiente
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="row g-4">
                {{-- Fechas y Vencimiento --}}
                <div class="col-12 col-md-6">
                    <h6 class="text-uppercase small text-secondary fw-bold mb-3">Información de Fechas</h6>
                    <div class="bg-light p-3 rounded-3 border">
                        <div class="d-flex justify-content-between py-1 border-bottom">
                            <span class="text-muted small">Fecha de Emisión:</span>
                            <span class="fw-semibold small">{{ $expense->issue_date->format('d/m/Y') }}</span>
                        </div>
                        <div class="d-flex justify-content-between py-1 border-bottom">
                            <span class="text-muted small">Fecha de Vencimiento:</span>
                            <span class="small">
                                @if($expense->due_date)
                                    <strong class="@if($expense->is_overdue) text-danger @endif">
                                        {{ $expense->due_date->format('d/m/Y') }}
                                    </strong>
                                    @if($expense->is_overdue)
                                        <span class="badge bg-danger ms-1" style="font-size: 10px;">Vencida</span>
                                    @endif
                                @else
                                    <span class="text-muted">Sin vencimiento</span>
                                @endif
                            </span>
                        </div>
                        @if($expense->status === 'paid' && $expense->paid_at)
                            <div class="d-flex justify-content-between py-1 border-bottom">
                                <span class="text-muted small">Fecha de Pago:</span>
                                <span class="fw-semibold text-success small">{{ $expense->paid_at->format('d/m/Y') }}</span>
                            </div>
                            <div class="d-flex justify-content-between py-1">
                                <span class="text-muted small">Método de Pago:</span>
                                <span class="fw-semibold small">{{ $expense->payment_method_label ?? 'Otro' }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Proveedor y Concepto --}}
                <div class="col-12 col-md-6">
                    <h6 class="text-uppercase small text-secondary fw-bold mb-3">Proveedor y Concepto</h6>
                    <div class="bg-light p-3 rounded-3 border">
                        <div class="d-flex justify-content-between py-1 border-bottom">
                            <span class="text-muted small">Proveedor:</span>
                            <span class="small">
                                @if($expense->supplier)
                                    <a href="{{ route('suppliers.show', $expense->supplier) }}" class="fw-semibold text-decoration-none">
                                        {{ $expense->supplier->name }}
                                    </a>
                                @else
                                    <span class="text-muted">No asignado / Gasto directo</span>
                                @endif
                            </span>
                        </div>
                        @if($expense->supplier && $expense->supplier->nit)
                            <div class="d-flex justify-content-between py-1 border-bottom">
                                <span class="text-muted small">NIT / Documento:</span>
                                <span class="small font-monospace">{{ $expense->supplier->nit }}</span>
                            </div>
                        @endif
                        <div class="py-2">
                            <span class="text-muted small d-block mb-1">Descripción / Concepto:</span>
                            <p class="small mb-0 text-dark">{{ $expense->description ?: 'Sin descripción detallada.' }}</p>
                        </div>
                    </div>
                </div>

                {{-- Comprobante Adjunto --}}
                <div class="col-12">
                    <h6 class="text-uppercase small text-secondary fw-bold mb-3">Comprobante / Factura Digitalizada</h6>
                    @if($expense->attachment_path)
                        <div class="p-3 bg-light border rounded-3 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
                            <div class="d-flex align-items-center gap-3">
                                <i class="bi bi-file-earmark-pdf text-danger fs-2"></i>
                                <div>
                                    <div class="fw-semibold text-dark">{{ $expense->attachment_original_name ?? 'Comprobante_Adjunto' }}</div>
                                    <small class="text-muted">Almacenado de forma privada en el servidor seguro</small>
                                </div>
                            </div>
                            <div>
                                <a href="{{ route('expenses.attachment', $expense) }}" target="_blank" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-download me-1"></i> Descargar Comprobante
                                </a>
                            </div>
                        </div>
                    @else
                        <div class="p-4 bg-light border rounded-3 text-center text-muted small">
                            <i class="bi bi-paperclip fs-3 d-block mb-1 text-secondary"></i>
                            No se adjuntó archivo digital para este gasto. Puedes subirlo editando el registro.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal Marcar como Pagada --}}
@if($expense->status === 'pending')
<div class="modal fade" id="payModal" tabindex="-1" aria-labelledby="payModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('expenses.pay', $expense) }}" class="modal-content shadow">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="payModalLabel">Marcar Factura como Pagada</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">Registra el pago de este gasto por valor de <strong>${{ number_format($expense->amount, 0, ',', '.') }}</strong>.</p>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Fecha de pago <span class="text-danger">*</span></label>
                    <input type="date" name="paid_at" class="form-control" value="{{ date('Y-m-d') }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Método de pago <span class="text-danger">*</span></label>
                    <select name="payment_method" class="form-select" required>
                        <option value="">Selecciona método de pago...</option>
                        @foreach($paymentMethods as $pm)
                            <option value="{{ $pm->value }}">{{ $pm->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success btn-sm">Confirmar Pago</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection
