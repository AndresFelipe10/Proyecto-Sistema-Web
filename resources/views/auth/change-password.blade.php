@extends('layouts.auth')

@section('title', 'Cambiar Contraseña')
@section('heading', 'Actualiza tu contraseña')
@section('subheading', 'Por seguridad, debes establecer una nueva contraseña antes de continuar')

@section('content')
<form method="POST" action="{{ route('password.change.update') }}" novalidate>
    @csrf

    <div class="mb-3">
        <label for="current_password" class="form-label small fw-semibold text-secondary">Contraseña actual o temporal</label>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-key text-muted"></i></span>
            <input type="password" 
                   class="form-control border-start-0 @error('current_password') is-invalid @enderror" 
                   id="current_password" 
                   name="current_password" 
                   placeholder="••••••••••••" 
                   required 
                   autofocus>
        </div>
        @error('current_password')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3">
        <label for="password" class="form-label small fw-semibold text-secondary">Nueva Contraseña (mínimo 8 caracteres)</label>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock text-muted"></i></span>
            <input type="password" 
                   class="form-control border-start-0 @error('password') is-invalid @enderror" 
                   id="password" 
                   name="password" 
                   placeholder="••••••••••••" 
                   required>
        </div>
        @error('password')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-4">
        <label for="password_confirmation" class="form-label small fw-semibold text-secondary">Confirma la Nueva Contraseña</label>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-shield-check text-muted"></i></span>
            <input type="password" 
                   class="form-control border-start-0" 
                   id="password_confirmation" 
                   name="password_confirmation" 
                   placeholder="••••••••••••" 
                   required>
        </div>
    </div>

    <div class="form-check mb-4">
        <input class="form-check-input @error('accept_terms') is-invalid @enderror" type="checkbox" name="accept_terms" id="accept_terms" value="1" required>
        <label class="form-check-label small text-secondary" for="accept_terms">
            He leído y acepto los 
            <a href="{{ route('legal.terms') }}" target="_blank" class="fw-semibold text-primary">Términos y Condiciones del Servicio</a> 
            y la 
            <a href="{{ route('legal.privacy') }}" target="_blank" class="fw-semibold text-primary">Política de Tratamiento de Datos Personales</a>.
        </label>
        @error('accept_terms')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
    </div>

    <button type="submit" class="btn btn-primary-custom">
        <i class="bi bi-check-circle me-1"></i> Guardar Nueva Contraseña
    </button>
</form>
@endsection

@section('footer')
    <form method="POST" action="{{ route('logout') }}" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-link text-muted p-0 text-decoration-none small">
            <i class="bi bi-box-arrow-left me-1"></i> Cerrar sesión
        </button>
    </form>
@endsection
