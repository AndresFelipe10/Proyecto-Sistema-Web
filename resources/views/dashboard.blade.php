@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="row g-4">
    <div class="col-12">
        <div class="card card-custom p-4 bg-white">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <h2 class="h4 fw-bold mb-1">¡Hola, {{ $user->name }}!</h2>
                    <p class="text-muted mb-0">Has iniciado sesión correctamente en el Sistema de Ventas e Inventario.</p>
                </div>
                <span class="badge bg-success-subtle text-success px-3 py-2 rounded-pill fs-6">
                    <i class="bi bi-shield-check me-1"></i> Sesión Autenticada
                </span>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card card-custom p-4 bg-white text-center">
            <div class="mb-3 text-primary">
                <i class="bi bi-shop fs-1"></i>
            </div>
            <h5 class="fw-bold">Emprendimientos</h5>
            <p class="text-muted small">Configura y gestiona tus empresas registradas.</p>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card card-custom p-4 bg-white text-center">
            <div class="mb-3 text-success">
                <i class="bi bi-boxes fs-1"></i>
            </div>
            <h5 class="fw-bold">Inventario</h5>
            <p class="text-muted small">Controla existencias, movimientos y alertas de stock.</p>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card card-custom p-4 bg-white text-center">
            <div class="mb-3 text-info">
                <i class="bi bi-receipt-cutoff fs-1"></i>
            </div>
            <h5 class="fw-bold">Ventas</h5>
            <p class="text-muted small">Registra ventas, clientes y emite comprobantes.</p>
        </div>
    </div>
</div>
@endsection
