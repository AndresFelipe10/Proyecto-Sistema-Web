@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="row g-4">
    <div class="col-12">
        <div class="card card-custom p-4 bg-white">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <h2 class="h4 fw-bold mb-1">¡Hola, {{ $user->name }}!</h2>
                    <p class="text-muted mb-0">
                        Gestionando el emprendimiento: 
                        <strong class="text-primary fs-6">{{ $currentBusiness->name }}</strong>
                        @if ($currentBusiness->nit)
                            <span class="badge bg-light text-dark ms-2 border">NIT: {{ $currentBusiness->nit }}</span>
                        @endif
                    </p>
                </div>
                <span class="badge bg-success-subtle text-success px-3 py-2 rounded-pill fs-6">
                    <i class="bi bi-shield-check me-1"></i> Multi-Tenancy Activo
                </span>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card card-custom p-4 bg-white text-center h-100">
            <div class="mb-3 text-primary">
                <i class="bi bi-shop fs-1"></i>
            </div>
            <h5 class="fw-bold">Emprendimientos</h5>
            <p class="text-muted small mb-3">Configura y gestiona tus empresas registradas.</p>
            <a href="{{ route('businesses.index') }}" class="btn btn-outline-primary btn-sm rounded-pill mt-auto">Administrar</a>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card card-custom p-4 bg-white text-center h-100">
            <div class="mb-3 text-success">
                <i class="bi bi-boxes fs-1"></i>
            </div>
            <h5 class="fw-bold">Inventario</h5>
            <p class="text-muted small mb-3">Controla existencias, movimientos y alertas de stock aisladas por negocio.</p>
            <span class="badge bg-secondary-subtle text-secondary rounded-pill py-2 px-3 mt-auto">Próxima Fase</span>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card card-custom p-4 bg-white text-center h-100">
            <div class="mb-3 text-info">
                <i class="bi bi-receipt-cutoff fs-1"></i>
            </div>
            <h5 class="fw-bold">Ventas</h5>
            <p class="text-muted small mb-3">Registra ventas, clientes y emite comprobantes específicos de tu empresa.</p>
            <span class="badge bg-secondary-subtle text-secondary rounded-pill py-2 px-3 mt-auto">Próxima Fase</span>
        </div>
    </div>
</div>
@endsection
