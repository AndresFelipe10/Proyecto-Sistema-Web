@extends('layouts.app')

@section('title', 'Vincular Colaborador')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card card-custom p-4 p-md-5 bg-white">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-1">Vincular Colaborador</h3>
                    <p class="text-muted small mb-0">Agrega un usuario registrado en la plataforma a tu emprendimiento.</p>
                </div>
                <a href="{{ route('users.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
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

            <form method="POST" action="{{ route('users.store') }}" novalidate>
                @csrf

                <div class="mb-3">
                    <label for="email" class="form-label small fw-semibold text-secondary">Correo electrónico del usuario <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-envelope"></i></span>
                        <input type="email" 
                               class="form-control @error('email') is-invalid @enderror" 
                               id="email" 
                               name="email" 
                               value="{{ old('email') }}" 
                               placeholder="colaborador@correo.com" 
                               required 
                               autofocus>
                    </div>
                    <div class="form-text small">El usuario debe haberse registrado previamente en el sistema.</div>
                </div>

                <div class="mb-4">
                    <label for="role_id" class="form-label small fw-semibold text-secondary">Rol asignado <span class="text-danger">*</span></label>
                    <select class="form-select @error('role_id') is-invalid @enderror" id="role_id" name="role_id" required>
                        <option value="" disabled {{ old('role_id') ? '' : 'selected' }}>Selecciona un rol...</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" {{ old('role_id') == $role->id ? 'selected' : '' }}>
                                {{ $role->name }} — {{ $role->description ?? ($role->slug === 'admin' ? 'Acceso total y configuración' : 'Ventas, inventario y clientes') }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('users.index') }}" class="btn btn-outline-secondary px-4 rounded-3">Cancelar</a>
                    <button type="submit" class="btn btn-primary px-4 rounded-3 fw-semibold">
                        <i class="bi bi-check-circle-fill me-1"></i> Vincular al Equipo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
