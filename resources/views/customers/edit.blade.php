@extends('layouts.app')

@section('title', 'Editar Cliente')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card card-custom p-4 p-md-5 bg-white">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-1">Editar Cliente</h3>
                    <p class="text-muted small mb-0">Modifica los datos comerciales de <strong>{{ $customer->name }}</strong>.</p>
                </div>
                <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
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

            <form method="POST" action="{{ route('customers.update', $customer) }}" novalidate>
                @csrf
                @method('PUT')

                <div class="row g-3 mb-3">
                    <div class="col-md-7">
                        <label for="name" class="form-label small fw-semibold text-secondary">Nombre o Razón Social <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control @error('name') is-invalid @enderror" 
                               id="name" 
                               name="name" 
                               value="{{ old('name', $customer->name) }}" 
                               required 
                               autofocus>
                    </div>

                    <div class="col-md-5">
                        <label for="identification_number" class="form-label small fw-semibold text-secondary">Cédula / NIT / Identificación</label>
                        <input type="text" 
                               class="form-control font-monospace @error('identification_number') is-invalid @enderror" 
                               id="identification_number" 
                               name="identification_number" 
                               value="{{ old('identification_number', $customer->identification_number) }}">
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="phone" class="form-label small fw-semibold text-secondary">Teléfono / WhatsApp</label>
                        <input type="text" 
                               class="form-control @error('phone') is-invalid @enderror" 
                               id="phone" 
                               name="phone" 
                               value="{{ old('phone', $customer->phone) }}">
                    </div>

                    <div class="col-md-6">
                        <label for="email" class="form-label small fw-semibold text-secondary">Correo Electrónico</label>
                        <input type="email" 
                               class="form-control @error('email') is-invalid @enderror" 
                               id="email" 
                               name="email" 
                               value="{{ old('email', $customer->email) }}">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="address" class="form-label small fw-semibold text-secondary">Dirección / Ubicación (Cali)</label>
                    <input type="text" 
                           class="form-control @error('address') is-invalid @enderror" 
                           id="address" 
                           name="address" 
                           value="{{ old('address', $customer->address) }}">
                </div>

                <div class="mb-4 form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" {{ old('is_active', $customer->is_active ? '1' : '0') == '1' ? 'checked' : '' }}>
                    <label class="form-check-label small fw-semibold text-secondary" for="is_active">Cliente activo</label>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary px-4 rounded-3">Cancelar</a>
                    <button type="submit" class="btn btn-primary px-4 rounded-3 fw-semibold">
                        <i class="bi bi-save me-1"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
