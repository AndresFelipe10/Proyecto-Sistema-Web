@extends('layouts.app')

@section('title', 'Registrar Proveedor')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card card-custom p-4 p-md-5 bg-white">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-1">Registrar Proveedor</h3>
                    <p class="text-muted small mb-0">Agrega un nuevo contacto de suministro a <strong>{{ $currentBusiness->name }}</strong>.</p>
                </div>
                <a href="{{ route('suppliers.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                    <i class="bi bi-arrow-left me-1"></i> Volver
                </a>
            </div>

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

            <form method="POST" action="{{ route('suppliers.store') }}" novalidate>
                @csrf

                <div class="row g-3 mb-3">
                    <div class="col-md-7">
                        <label for="name" class="form-label small fw-semibold text-secondary">Nombre de la Empresa o Razón Social <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control @error('name') is-invalid @enderror" 
                               id="name" 
                               name="name" 
                               value="{{ old('name') }}" 
                               placeholder="Ej. Distribuidora Cuero Valle SAS" 
                               required 
                               autofocus>
                    </div>

                    <div class="col-md-5">
                        <label for="identification_number" class="form-label small fw-semibold text-secondary">NIT / Identificación Tributaria</label>
                        <input type="text" 
                               class="form-control font-monospace @error('identification_number') is-invalid @enderror" 
                               id="identification_number" 
                               name="identification_number" 
                               value="{{ old('identification_number') }}" 
                               placeholder="Ej. 900876543-2">
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="contact_name" class="form-label small fw-semibold text-secondary">Persona de Contacto</label>
                        <input type="text" 
                               class="form-control @error('contact_name') is-invalid @enderror" 
                               id="contact_name" 
                               name="contact_name" 
                               value="{{ old('contact_name') }}" 
                               placeholder="Ej. Carlos Gómez (Asesor Comercial)">
                    </div>

                    <div class="col-md-6">
                        <label for="phone" class="form-label small fw-semibold text-secondary">Teléfono / WhatsApp</label>
                        <input type="text" 
                               class="form-control @error('phone') is-invalid @enderror" 
                               id="phone" 
                               name="phone" 
                               value="{{ old('phone') }}" 
                               placeholder="Ej. 312 987 6543">
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="email" class="form-label small fw-semibold text-secondary">Correo Electrónico</label>
                        <input type="email" 
                               class="form-control @error('email') is-invalid @enderror" 
                               id="email" 
                               name="email" 
                               value="{{ old('email') }}" 
                               placeholder="ventas@proveedor.com">
                    </div>

                    <div class="col-md-6">
                        <label for="address" class="form-label small fw-semibold text-secondary">Dirección / Ubicación</label>
                        <input type="text" 
                               class="form-control @error('address') is-invalid @enderror" 
                               id="address" 
                               name="address" 
                               value="{{ old('address') }}" 
                               placeholder="Ej. Calle 15 # 10-25, Cali">
                    </div>
                </div>

                <div class="mb-4 form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" {{ old('is_active', '1') == '1' ? 'checked' : '' }}>
                    <label class="form-check-label small fw-semibold text-secondary" for="is_active">Proveedor activo</label>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('suppliers.index') }}" class="btn btn-outline-secondary px-4 rounded-3">Cancelar</a>
                    <button type="submit" class="btn btn-primary px-4 rounded-3 fw-semibold">
                        <i class="bi bi-check-circle-fill me-1"></i> Guardar Proveedor
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
