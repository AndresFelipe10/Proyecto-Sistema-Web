@extends('layouts.app')

@section('title', 'Registrar Emprendimiento')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card card-custom p-4 p-md-5 bg-white">
            <div class="text-center mb-4">
                <div class="badge bg-primary-subtle text-primary mb-2 px-3 py-2 rounded-pill fs-6">
                    <i class="bi bi-shop me-1"></i> Nuevo Emprendimiento
                </div>
                <h3 class="fw-bold">Registra tu Emprendimiento</h3>
                <p class="text-muted small">Configura la información básica de tu negocio para comenzar a gestionar ventas e inventario.</p>
            </div>

            @if (session('info'))
                <div class="alert alert-info d-flex align-items-center mb-4 py-2 px-3 small rounded-3">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    <div>{{ session('info') }}</div>
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger mb-4 py-2 px-3 small rounded-3">
                    <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> Corrige los siguientes errores:</div>
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('businesses.store') }}" novalidate>
                @csrf

                <div class="mb-3">
                    <label for="name" class="form-label small fw-semibold text-secondary">Nombre comercial del emprendimiento <span class="text-danger">*</span></label>
                    <input type="text" 
                           class="form-control @error('name') is-invalid @enderror" 
                           id="name" 
                           name="name" 
                           value="{{ old('name') }}" 
                           placeholder="Ej. Calzado La Sultana" 
                           required 
                           autofocus>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="nit" class="form-label small fw-semibold text-secondary">NIT / Identificación Tributaria</label>
                        <input type="text" 
                               class="form-control @error('nit') is-invalid @enderror" 
                               id="nit" 
                               name="nit" 
                               value="{{ old('nit') }}" 
                               placeholder="Ej. 900123456-7">
                    </div>

                    <div class="col-md-6">
                        <label for="phone" class="form-label small fw-semibold text-secondary">Teléfono / WhatsApp</label>
                        <input type="text" 
                               class="form-control @error('phone') is-invalid @enderror" 
                               id="phone" 
                               name="phone" 
                               value="{{ old('phone') }}" 
                               placeholder="Ej. 300 123 4567">
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="email" class="form-label small fw-semibold text-secondary">Correo Electrónico de Contacto</label>
                        <input type="email" 
                               class="form-control @error('email') is-invalid @enderror" 
                               id="email" 
                               name="email" 
                               value="{{ old('email') }}" 
                               placeholder="contacto@negocio.com">
                    </div>

                    <div class="col-md-6">
                        <label for="address" class="form-label small fw-semibold text-secondary">Dirección / Ubicación (Cali)</label>
                        <input type="text" 
                               class="form-control @error('address') is-invalid @enderror" 
                               id="address" 
                               name="address" 
                               value="{{ old('address') }}" 
                               placeholder="Ej. Calle 5 # 38-20, Cali">
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    @if (isset($currentBusiness))
                        <a href="{{ route('businesses.index') }}" class="btn btn-outline-secondary px-4 rounded-3">Cancelar</a>
                    @endif
                    <button type="submit" class="btn btn-primary px-4 rounded-3 fw-semibold">
                        <i class="bi bi-check-circle-fill me-1"></i> Guardar y Activar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
