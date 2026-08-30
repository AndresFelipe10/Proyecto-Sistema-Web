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
    </div>
</div>
@endsection
