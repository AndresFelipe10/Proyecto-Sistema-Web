@extends('layouts.superadmin')

@section('title', 'Crear Negocio')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card card-dark p-4 p-md-5">
            <div class="d-flex justify-content-between align-items-center mb-4 border-bottom border-secondary pb-3">
                <div>
                    <h3 class="fw-bold mb-1">Registrar Nuevo Emprendimiento</h3>
                    <p class="text-secondary small mb-0">Crea en un solo paso el negocio y su usuario administrador inicial.</p>
                </div>
                <a href="{{ route('superadmin.businesses.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                    <i class="bi bi-arrow-left me-1"></i> Volver
                </a>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger mb-4 py-2 px-3 small rounded-3 bg-danger bg-opacity-25 text-danger-emphasis border-0">
                    <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> Corrige los siguientes errores:</div>
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('superadmin.businesses.store') }}" novalidate>
                @csrf

                <h5 class="fw-bold text-primary mb-3"><i class="bi bi-building me-2"></i>1. Datos del Negocio</h5>

                <div class="mb-3">
                    <label for="name" class="form-label small fw-semibold text-secondary">Nombre Comercial <span class="text-danger">*</span></label>
                    <input type="text" class="form-control bg-dark border-secondary text-light @error('name') is-invalid @enderror"
                           id="name" name="name" value="{{ old('name') }}" placeholder="Ej: Tienda La Colina" required autofocus>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="nit" class="form-label small fw-semibold text-secondary">NIT / Identificación</label>
                        <input type="text" class="form-control bg-dark border-secondary text-light @error('nit') is-invalid @enderror"
                               id="nit" name="nit" value="{{ old('nit') }}" placeholder="Ej: 900123456-1">
                    </div>
                    <div class="col-md-6">
                        <label for="phone" class="form-label small fw-semibold text-secondary">Teléfono / WhatsApp</label>
                        <input type="text" class="form-control bg-dark border-secondary text-light @error('phone') is-invalid @enderror"
                               id="phone" name="phone" value="{{ old('phone') }}" placeholder="Ej: 3001234567">
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="email" class="form-label small fw-semibold text-secondary">Correo del Negocio</label>
                        <input type="email" class="form-control bg-dark border-secondary text-light @error('email') is-invalid @enderror"
                               id="email" name="email" value="{{ old('email') }}" placeholder="contacto@negocio.com">
                    </div>
                    <div class="col-md-6">
                        <label for="address" class="form-label small fw-semibold text-secondary">Dirección</label>
                        <input type="text" class="form-control bg-dark border-secondary text-light @error('address') is-invalid @enderror"
                               id="address" name="address" value="{{ old('address') }}" placeholder="Ej: Calle 5 # 10-20, Cali">
                    </div>
                </div>

                <h5 class="fw-bold text-primary mb-3 border-top border-secondary pt-4"><i class="bi bi-person-badge me-2"></i>2. Administrador Inicial</h5>

                <div class="mb-3">
                    <label for="admin_name" class="form-label small fw-semibold text-secondary">Nombre del Administrador <span class="text-danger">*</span></label>
                    <input type="text" class="form-control bg-dark border-secondary text-light @error('admin_name') is-invalid @enderror"
                           id="admin_name" name="admin_name" value="{{ old('admin_name') }}" placeholder="Ej: Andrés Felipe Gómez" required>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="admin_email" class="form-label small fw-semibold text-secondary">Correo del Administrador <span class="text-danger">*</span></label>
                        <input type="email" class="form-control bg-dark border-secondary text-light @error('admin_email') is-invalid @enderror"
                               id="admin_email" name="admin_email" value="{{ old('admin_email') }}" placeholder="admin@negocio.com" required>
                    </div>
                    <div class="col-md-6">
                        <label for="admin_password" class="form-label small fw-semibold text-secondary">Contraseña Inicial</label>
                        <input type="text" class="form-control bg-dark border-secondary text-light @error('admin_password') is-invalid @enderror"
                               id="admin_password" name="admin_password" value="{{ old('admin_password') }}"
                               placeholder="Opcional: dejar vacío para autogenerar">
                        <div class="form-text text-secondary small">Si la dejas en blanco, el sistema generará una segura y la mostrará una sola vez.</div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 border-top border-secondary pt-3">
                    <a href="{{ route('superadmin.businesses.index') }}" class="btn btn-outline-secondary px-4">Cancelar</a>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold">
                        <i class="bi bi-check-circle me-1"></i> Crear Negocio y Administrador
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
