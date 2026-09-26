@extends('layouts.auth')

@section('title', 'Iniciar Sesión')
@section('heading', 'Bienvenido de nuevo')
@section('subheading', 'Ingresa tus credenciales para acceder a tu panel')

@section('content')
<form method="POST" action="{{ route('login') }}" novalidate>
    @csrf

    <div class="mb-3">
        <label for="email" class="form-label small fw-semibold text-secondary">Correo Electrónico</label>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope text-muted"></i></span>
            <input type="email" 
                   class="form-control border-start-0 @error('email') is-invalid @enderror" 
                   id="email" 
                   name="email" 
                   value="{{ old('email') }}" 
                   placeholder="ejemplo@negocio.com" 
                   required 
                   autofocus 
                   autocomplete="username">
        </div>
    </div>

    <div class="mb-3">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <label for="password" class="form-label small fw-semibold text-secondary mb-0">Contraseña</label>
            <a href="{{ route('password.request') }}" class="small text-decoration-none text-primary fw-medium">¿Olvidaste tu clave?</a>
        </div>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock text-muted"></i></span>
            <input type="password" 
                   class="form-control border-start-0 @error('password') is-invalid @enderror" 
                   id="password" 
                   name="password" 
                   placeholder="••••••••" 
                   required 
                   autocomplete="current-password">
        </div>
    </div>

    <div class="mb-4 form-check">
        <input type="checkbox" class="form-check-input" id="remember" name="remember" value="1">
        <label class="form-check-label small text-secondary" for="remember">Recordar mi sesión en este equipo</label>
    </div>

    <button type="submit" class="btn btn-primary-custom">
        <i class="bi bi-box-arrow-in-right me-1"></i> Iniciar Sesión
    </button>
</form>
@endsection

@section('footer')
    ¿No tienes una cuenta aún? 
    <a href="{{ route('register') }}" class="text-primary text-decoration-none fw-semibold">Crear cuenta</a>
@endsection
