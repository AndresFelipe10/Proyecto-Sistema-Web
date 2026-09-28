@extends('layouts.superadmin')

@section('title', 'Panel de Plataforma')

@section('content')
<div class="mb-4">
    <h3 class="fw-bold mb-1">Panel de Control de Plataforma</h3>
    <p class="text-secondary small mb-0">Monitoreo general de negocios y usuarios en PuntoStock</p>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card card-dark p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-secondary small fw-semibold text-uppercase">Negocios Activos</span>
                <i class="bi bi-shop fs-4 text-success"></i>
            </div>
            <div class="fs-2 fw-bold text-success">{{ number_format($activeBusinesses) }}</div>
            <div class="small text-secondary mt-1">Operando normalmente</div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card card-dark p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-secondary small fw-semibold text-uppercase">Negocios Suspendidos</span>
                <i class="bi bi-pause-circle fs-4 text-warning"></i>
            </div>
            <div class="fs-2 fw-bold text-warning">{{ number_format($suspendedBusinesses) }}</div>
            <div class="small text-secondary mt-1">Acceso restringido por administración</div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card card-dark p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-secondary small fw-semibold text-uppercase">Total Usuarios</span>
                <i class="bi bi-people fs-4 text-primary"></i>
            </div>
            <div class="fs-2 fw-bold text-white">{{ number_format($totalUsers) }}</div>
            <div class="small text-secondary mt-1">Usuarios registrados en la base de datos</div>
        </div>
    </div>
</div>

<div class="card card-dark p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0"><i class="bi bi-lightning-charge text-warning me-2"></i>Acciones Rápidas</h5>
    </div>
    <div class="d-flex gap-3 flex-wrap">
        <a href="{{ route('superadmin.businesses.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> Crear Nuevo Negocio
        </a>
        <a href="{{ route('superadmin.businesses.index') }}" class="btn btn-outline-light">
            <i class="bi bi-buildings me-1"></i> Gestionar Negocios
        </a>
    </div>
</div>
@endsection
