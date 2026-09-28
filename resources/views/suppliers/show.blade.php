@extends('layouts.app')

@section('title', $supplier->name)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div class="d-flex align-items-center gap-3">
        <a href="{{ route('suppliers.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            <i class="bi bi-arrow-left me-1"></i> Volver al Directorio
        </a>
        <div>
            <h3 class="fw-bold mb-0">{{ $supplier->name }}</h3>
            @if ($supplier->identification_number)
                <span class="badge bg-light text-dark font-monospace border">NIT: {{ $supplier->identification_number }}</span>
            @endif
        </div>
    </div>

    @can('update', $supplier)
        <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-primary rounded-pill px-4 fw-semibold">
            <i class="bi bi-pencil-square me-1"></i> Editar Proveedor
        </a>
    @endcan
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card card-custom p-4 bg-white mb-4">
            <h5 class="fw-bold mb-3 border-bottom pb-2">Información del Proveedor</h5>

            <div class="row g-3">
                <div class="col-sm-6 mb-2">
                    <span class="text-muted small d-block">Razón Social</span>
                    <span class="fw-semibold">{{ $supplier->name }}</span>
                </div>
                <div class="col-sm-6 mb-2">
                    <span class="text-muted small d-block">NIT / Identificación Tributaria</span>
                    <span class="fw-semibold font-monospace">{{ $supplier->identification_number ?? 'No especificado' }}</span>
                </div>
                <div class="col-sm-6 mb-2">
                    <span class="text-muted small d-block">Persona de Contacto</span>
                    <span class="fw-semibold">{{ $supplier->contact_name ?? 'No especificada' }}</span>
                </div>
                <div class="col-sm-6 mb-2">
                    <span class="text-muted small d-block">Teléfono / WhatsApp</span>
                    <span class="fw-semibold">{{ $supplier->phone ?? 'No registrado' }}</span>
                </div>
                <div class="col-sm-6 mb-2">
                    <span class="text-muted small d-block">Correo Electrónico</span>
                    <span class="fw-semibold">{{ $supplier->email ?? 'No registrado' }}</span>
                </div>
                <div class="col-sm-6 mb-2">
                    <span class="text-muted small d-block">Dirección Comercial</span>
                    <span class="fw-semibold">{{ $supplier->address ?? 'No registrada' }}</span>
                </div>
                <div class="col-12 border-top pt-2">
                    <span class="text-muted small d-block">Estado</span>
                    @if ($supplier->is_active)
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1">
                            <i class="bi bi-check-circle-fill me-1"></i> Activo
                        </span>
                    @else
                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2.5 py-1">
                            <i class="bi bi-dash-circle-fill me-1"></i> Inactivo
                        </span>
                    @endif
                </div>
            </div>
        </div>

        @if(auth()->check() && auth()->user()->isCurrentAdmin())
            {{-- Sección de Gastos y Facturas Asociadas (Solo Administrador) --}}
            <div class="card card-custom p-4 bg-white mb-4">
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 border-bottom pb-3 mb-3">
                    <div>
                        <h5 class="fw-bold mb-0">Facturas y Gastos Asociados</h5>
                        <small class="text-muted">Historial de compras y obligaciones financieras con este proveedor.</small>
                    </div>
                    <a href="{{ route('expenses.create') }}?supplier_id={{ $supplier->id }}" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                        <i class="bi bi-plus-circle me-1"></i> Registrar Factura
                    </a>
                </div>

                {{-- Balance de obligaciones --}}
                <div class="row g-3 mb-3">
                    <div class="col-sm-6">
                        <div class="p-3 bg-light rounded-3 border">
                            <span class="text-muted small d-block">Total Facturado</span>
                            <span class="fs-5 fw-bold text-dark">${{ number_format($totalExpensesAmount, 0, ',', '.') }}</span>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-3 bg-light rounded-3 border border-warning">
                            <span class="text-muted small d-block">Saldo Por Pagar / Pendiente</span>
                            <span class="fs-5 fw-bold text-warning">${{ number_format($pendingAmount, 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>

                {{-- Tabla de gastos asociados --}}
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light text-secondary">
                            <tr>
                                <th>Emisión</th>
                                <th>Factura</th>
                                <th>Categoría</th>
                                <th class="text-end">Monto</th>
                                <th>Estado</th>
                                <th class="text-end">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($supplier->expenses as $exp)
                                <tr>
                                    <td>{{ $exp->issue_date->format('d/m/Y') }}</td>
                                    <td>
                                        @if($exp->invoice_number)
                                            <span class="badge bg-light text-dark border font-monospace">{{ $exp->invoice_number }}</span>
                                        @else
                                            <span class="text-muted">S/N</span>
                                        @endif
                                    </td>
                                    <td>{{ $exp->category_label }}</td>
                                    <td class="text-end fw-bold">${{ number_format($exp->amount, 0, ',', '.') }}</td>
                                    <td>
                                        @if($exp->status === 'paid')
                                            <span class="badge bg-success-subtle text-success">Pagada</span>
                                        @elseif($exp->is_overdue)
                                            <span class="badge bg-danger-subtle text-danger">Vencida</span>
                                        @else
                                            <span class="badge bg-warning-subtle text-warning">Pendiente</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('expenses.show', $exp) }}" class="btn btn-outline-secondary btn-sm py-0 px-2" title="Ver detalle">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        No hay facturas ni gastos registrados para este proveedor.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
