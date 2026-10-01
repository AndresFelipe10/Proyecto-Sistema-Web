@extends('layouts.superadmin')

@section('title', 'Agregar Colaborador - ' . $business->name)

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card card-dark p-4 p-md-5 shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-4 border-bottom border-secondary pb-3">
                <div>
                    <h3 class="fw-bold mb-1">Agregar Colaborador</h3>
                    <p class="text-secondary small mb-0">Vincula un nuevo usuario al negocio <strong>{{ $business->name }}</strong>.</p>
                </div>
                <a href="{{ route('superadmin.businesses.users', $business) }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
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

            <form method="POST" action="{{ route('superadmin.businesses.users.store', $business) }}" novalidate>
                @csrf

                <div class="mb-3">
                    <label for="name" class="form-label small fw-semibold text-secondary">Nombre Completo <span class="text-danger">*</span></label>
                    <input type="text" class="form-control bg-dark border-secondary text-light @error('name') is-invalid @enderror"
                           id="name" name="name" value="{{ old('name') }}" placeholder="Ej: Laura Martínez" required autofocus>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label small fw-semibold text-secondary">Correo Electrónico <span class="text-danger">*</span></label>
                    <input type="email" class="form-control bg-dark border-secondary text-light @error('email') is-invalid @enderror"
                           id="email" name="email" value="{{ old('email') }}" placeholder="correo@ejemplo.com" required>
                    <div class="form-text text-secondary small">Debe ser único en la plataforma. Servirá para iniciar sesión.</div>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="role" class="form-label small fw-semibold text-secondary">Rol en el Negocio <span class="text-danger">*</span></label>
                    <select class="form-select bg-dark border-secondary text-light @error('role') is-invalid @enderror"
                            id="role" name="role" required>
                        <option value="" disabled {{ old('role') ? '' : 'selected' }}>-- Selecciona el rol del usuario --</option>
                        <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>
                            Administrador (Control total del comercio, catálogo, inventario y configuración)
                        </option>
                        <option value="employee" {{ old('role') === 'employee' ? 'selected' : '' }}>
                            Empleado (Operativo diario: ventas POS, comandas de mesas, cobros y arqueo)
                        </option>
                    </select>
                    @error('role')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label small fw-semibold text-secondary">
                        Contraseña Temporal <span class="badge bg-secondary bg-opacity-25 text-secondary border">Opcional</span>
                    </label>
                    <input type="text" class="form-control bg-dark border-secondary text-light @error('password') is-invalid @enderror"
                           id="password" name="password" value="{{ old('password') }}" placeholder="Dejar en blanco para autogenerar una clave segura">
                    <div class="form-text text-secondary small">Si se deja vacío, el sistema generará una contraseña aleatoria de 16 caracteres.</div>
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="alert alert-info bg-info bg-opacity-10 border border-info border-opacity-25 text-light small mb-4 rounded-3 d-flex align-items-center">
                    <i class="bi bi-info-circle-fill text-info fs-5 me-2"></i>
                    <div>
                        El nuevo colaborador tendrá marcado el cambio obligatorio de contraseña (<code>must_change_password</code>) al iniciar sesión por primera vez.
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 pt-2 border-top border-secondary">
                    <a href="{{ route('superadmin.businesses.users', $business) }}" class="btn btn-outline-secondary px-4">
                        Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary fw-semibold px-4">
                        <i class="bi bi-person-check me-1"></i> Guardar Colaborador
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
