@extends('layouts.app')

@section('title', 'Gastos y Facturas de Compra')

@section('content')
<div class="container-fluid px-4 py-3">
    {{-- Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h3 class="fw-bold mb-1">Gastos y Facturas de Compra</h3>
            <p class="text-muted small mb-0">Control de egresos operativos, facturas de proveedores y cuentas por pagar de <strong>{{ $currentBusiness->name }}</strong>.</p>
        </div>
        <div>
            <a href="{{ route('expenses.create') }}" class="btn btn-primary shadow-sm">
                <i class="bi bi-plus-circle me-1"></i> Registrar Gasto / Factura
            </a>
        </div>
    </div>

    {{-- Alert de regla contable --}}
    <div class="alert alert-light border border-secondary-subtle d-flex align-items-center gap-2 py-2 px-3 mb-4 rounded-3 small">
        <i class="bi bi-info-circle text-primary fs-5"></i>
        <span><strong>Regla contable:</strong> El registro de gastos de mercancía no altera el inventario físico. Las existencias se administran exclusivamente desde el módulo de <strong>Inventario</strong>.</span>
    </div>

    {{-- Tarjetas de Resumen Consolidado --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body p-3">
                    <span class="text-muted small text-uppercase fw-semibold">Total Gastos (Filtro)</span>
                    <h4 class="fw-bold text-dark mt-2 mb-0">${{ number_format($summary['total_amount'], 0, ',', '.') }}</h4>
                    <span class="badge bg-secondary-subtle text-secondary mt-2">{{ $summary['count'] }} registros</span>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 h-100 border-start border-success border-4">
                <div class="card-body p-3">
                    <span class="text-muted small text-uppercase fw-semibold">Pagado</span>
                    <h4 class="fw-bold text-success mt-2 mb-0">${{ number_format($summary['paid_amount'], 0, ',', '.') }}</h4>
                    <small class="text-muted">Egresos liquidados</small>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 h-100 border-start border-warning border-4">
                <div class="card-body p-3">
                    <span class="text-muted small text-uppercase fw-semibold">Por Pagar / Pendiente</span>
                    <h4 class="fw-bold text-warning mt-2 mb-0">${{ number_format($summary['pending_amount'], 0, ',', '.') }}</h4>
                    <small class="text-muted">Cuentas pendientes</small>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 h-100 border-start border-danger border-4">
                <div class="card-body p-3">
                    <span class="text-muted small text-uppercase fw-semibold">Vencidas</span>
                    <h4 class="fw-bold text-danger mt-2 mb-0">${{ number_format($summary['overdue_amount'], 0, ',', '.') }}</h4>
                    <span class="badge bg-danger-subtle text-danger mt-2">{{ $summary['overdue_count'] }} facturas vencidas</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('expenses.index') }}" class="row g-2 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label small fw-semibold mb-1">Buscar</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="N° Factura o descripción..." value="{{ $filters['search'] ?? '' }}">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-semibold mb-1">Categoría</label>
                    <select name="category" class="form-select form-control-sm">
                        <option value="">Todas las categorías</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->value }}" @selected(($filters['category'] ?? '') === $cat->value)>{{ $cat->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-semibold mb-1">Proveedor</label>
                    <select name="supplier_id" class="form-select form-control-sm">
                        <option value="">Todos los proveedores</option>
                        @foreach($suppliers as $sup)
                            <option value="{{ $sup->id }}" @selected(($filters['supplier_id'] ?? '') == $sup->id)>{{ $sup->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-semibold mb-1">Estado</label>
                    <select name="status" class="form-select form-control-sm">
                        <option value="">Todos los estados</option>
                        <option value="paid" @selected(($filters['status'] ?? '') === 'paid')>Pagada</option>
                        <option value="pending" @selected(($filters['status'] ?? '') === 'pending')>Pendiente</option>
                        <option value="vencida" @selected(($filters['status'] ?? '') === 'vencida')>Vencida</option>
                    </select>
                </div>
                <div class="col-6 col-md-1">
                    <label class="form-label small fw-semibold mb-1">Mes</label>
                    <select name="month" class="form-select form-control-sm">
                        <option value="">Mes</option>
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" @selected(($filters['month'] ?? '') == $m)>{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-6 col-md-1">
                    <label class="form-label small fw-semibold mb-1">Año</label>
                    <select name="year" class="form-select form-control-sm">
                        <option value="">Año</option>
                        @for($y = date('Y'); $y >= date('Y') - 4; $y--)
                            <option value="{{ $y }}" @selected(($filters['year'] ?? '') == $y)>{{ $y }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-12 col-md-1 d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm w-100" title="Filtrar">
                        <i class="bi bi-funnel"></i>
                    </button>
                    @if(!empty(array_filter($filters)))
                        <a href="{{ route('expenses.index') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- Tabla de Gastos --}}
    <div class="card border-0 shadow-sm rounded-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary small text-uppercase">
                    <tr>
                        <th style="min-width: 100px;">Emisión</th>
                        <th style="min-width: 110px;">Factura</th>
                        <th>Proveedor</th>
                        <th>Categoría</th>
                        <th>Descripción</th>
                        <th class="text-end">Monto</th>
                        <th>Estado / Vencimiento</th>
                        <th class="text-center">Adjunto</th>
                        <th class="text-end" style="min-width: 140px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($expenses as $expense)
                        <tr>
                            <td class="small">{{ $expense->issue_date->format('d/m/Y') }}</td>
                            <td>
                                @if($expense->invoice_number)
                                    <span class="badge bg-light text-dark border font-monospace">{{ $expense->invoice_number }}</span>
                                @else
                                    <span class="text-muted small">Sin número</span>
                                @endif
                            </td>
                            <td>
                                @if($expense->supplier)
                                    <a href="{{ route('suppliers.show', $expense->supplier) }}" class="fw-semibold text-decoration-none">
                                        {{ $expense->supplier->name }}
                                    </a>
                                @else
                                    <span class="text-muted small">N/A</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                    {{ $expense->category_label }}
                                </span>
                            </td>
                            <td class="small text-muted" style="max-width: 200px;">
                                <div class="text-truncate" title="{{ $expense->description }}">{{ $expense->description ?? '—' }}</div>
                            </td>
                            <td class="text-end fw-bold text-dark">
                                ${{ number_format($expense->amount, 0, ',', '.') }}
                            </td>
                            <td>
                                @if($expense->status === 'paid')
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                        <i class="bi bi-check-circle me-1"></i>Pagada
                                    </span>
                                    @if($expense->paid_at)
                                        <br><small class="text-muted" style="font-size: 11px;">{{ $expense->paid_at->format('d/m/Y') }}</small>
                                    @endif
                                @elseif($expense->is_overdue)
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle" title="Venció el {{ $expense->due_date->format('d/m/Y') }}">
                                        <i class="bi bi-exclamation-triangle me-1"></i>Vencida
                                    </span>
                                    <br><small class="text-danger fw-semibold" style="font-size: 11px;">{{ $expense->due_date->format('d/m/Y') }}</small>
                                @else
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                                        <i class="bi bi-clock me-1"></i>Pendiente
                                    </span>
                                    @if($expense->due_date)
                                        <br><small class="text-muted" style="font-size: 11px;">Vence: {{ $expense->due_date->format('d/m/Y') }}</small>
                                    @endif
                                @endif
                            </td>
                            <td class="text-center">
                                @if($expense->attachment_path)
                                    <a href="{{ route('expenses.attachment', $expense) }}" class="btn btn-sm btn-light border text-primary" title="Descargar comprobante" target="_blank">
                                        <i class="bi bi-paperclip"></i>
                                    </a>
                                @else
                                    <span class="text-muted" style="font-size: 12px;">—</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('expenses.show', $expense) }}" class="btn btn-outline-secondary" title="Ver detalle">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    @if($expense->status === 'pending')
                                        <button type="button" class="btn btn-outline-success" title="Marcar como pagada"
                                            onclick="openPayModal({{ $expense->id }}, '{{ $expense->invoice_number ?? 'S/N' }}', '{{ number_format($expense->amount, 0, ',', '.') }}')">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                    @endif
                                    <a href="{{ route('expenses.edit', $expense) }}" class="btn btn-outline-secondary" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form action="{{ route('expenses.destroy', $expense) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Seguro que deseas eliminar este registro de gasto?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger" title="Eliminar">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="bi bi-receipt fs-1 d-block mb-2 text-secondary"></i>
                                No se encontraron gastos ni facturas de compra registradas con los filtros seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($expenses->hasPages())
            <div class="card-footer bg-white border-0 py-3">
                {{ $expenses->links() }}
            </div>
        @endif
    </div>
</div>

{{-- Modal Marcar como Pagada --}}
<div class="modal fade" id="payModal" tabindex="-1" aria-labelledby="payModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form id="payForm" method="POST" action="" class="modal-content shadow">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="payModalLabel">Marcar Factura como Pagada</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">Registra el pago de la factura <strong id="modalInvoice"></strong> por valor de <strong id="modalAmount"></strong>.</p>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Fecha de pago <span class="text-danger">*</span></label>
                    <input type="date" name="paid_at" class="form-control" value="{{ date('Y-m-d') }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Método de pago <span class="text-danger">*</span></label>
                    <select name="payment_method" class="form-select" required>
                        <option value="">Selecciona método de pago...</option>
                        <option value="cash">Efectivo</option>
                        <option value="transfer">Transferencia / Nequi / Daviplata</option>
                        <option value="card">Tarjeta débito/crédito</option>
                        <option value="other">Otro</option>
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

<script>
function openPayModal(expenseId, invoiceNumber, amount) {
    var form = document.getElementById('payForm');
    form.action = '/expenses/' + expenseId + '/pay';
    document.getElementById('modalInvoice').textContent = invoiceNumber;
    document.getElementById('modalAmount').textContent = '$' + amount;
    var modal = new bootstrap.Modal(document.getElementById('payModal'));
    modal.show();
}
</script>
@endsection
