@extends('layouts.auth')

@section('title', 'Crear Cuenta')
@section('heading', 'Crea tu cuenta')
@section('subheading', 'Comienza a gestionar tu emprendimiento en minutos')

@section('content')
<form method="POST" action="{{ route('register') }}" novalidate>
    @csrf

    <div class="mb-3">
        <label for="name" class="form-label small fw-semibold text-secondary">Nombre Completo</label>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-person text-muted"></i></span>
            <input type="text" 
                   class="form-control border-start-0 @error('name') is-invalid @enderror" 
                   id="name" 
                   name="name" 
                   value="{{ old('name') }}" 
                   placeholder="Juan Pérez" 
                   required 
                   autofocus 
                   autocomplete="name">
        </div>
    </div>

    <div class="mb-3">
        <label for="email" class="form-label small fw-semibold text-secondary">Correo Electrónico</label>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope text-muted"></i></span>
            <input type="email" 
                   class="form-control border-start-0 @error('email') is-invalid @enderror" 
                   id="email" 
                   name="email" 
                   value="{{ old('email') }}" 
                   placeholder="juan@ejemplo.com" 
                   required 
                   autocomplete="username">
        </div>
    </div>

    <div class="mb-3">
        <label for="password" class="form-label small fw-semibold text-secondary">Contraseña (mínimo 8 caracteres)</label>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock text-muted"></i></span>
            <input type="password" 
                   class="form-control border-start-0 @error('password') is-invalid @enderror" 
                   id="password" 
                   name="password" 
                   placeholder="••••••••" 
                   required 
                   autocomplete="new-password">
        </div>
    </div>

    <div class="mb-4">
        <label for="password_confirmation" class="form-label small fw-semibold text-secondary">Confirmar Contraseña</label>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-shield-check text-muted"></i></span>
            <input type="password" 
                   class="form-control border-start-0" 
                   id="password_confirmation" 
                   name="password_confirmation" 
                   placeholder="••••••••" 
                   required 
                   autocomplete="new-password">
        </div>
    </div>

    <button type="submit" class="btn btn-primary-custom">
        <i class="bi bi-person-plus-fill me-1"></i> Registrarme
    </button>
</form>
@endsection

@section('footer')
    ¿Ya tienes cuenta? 
    <a href="{{ route('login') }}" class="text-primary text-decoration-none fw-semibold">Iniciar sesión</a>
@endsection
