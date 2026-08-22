@extends('layouts.auth')

@section('title', 'Restablecer Contraseña')
@section('heading', 'Crea una nueva contraseña')
@section('subheading', 'Ingresa tu nueva contraseña para acceder a tu cuenta')

@section('content')
<form method="POST" action="{{ route('password.update') }}" novalidate>
    @csrf

    <input type="hidden" name="token" value="{{ $token }}">

    <div class="mb-3">
        <label for="email" class="form-label small fw-semibold text-secondary">Correo Electrónico</label>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope text-muted"></i></span>
            <input type="email" 
                   class="form-control border-start-0 @error('email') is-invalid @enderror" 
                   id="email" 
                   name="email" 
                   value="{{ old('email', $request->email) }}" 
                   required 
                   autofocus 
                   autocomplete="username">
        </div>
    </div>

    <div class="mb-3">
        <label for="password" class="form-label small fw-semibold text-secondary">Nueva Contraseña</label>
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
        <label for="password_confirmation" class="form-label small fw-semibold text-secondary">Confirmar Nueva Contraseña</label>
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
        <i class="bi bi-key-fill me-1"></i> Guardar Nueva Contraseña
    </button>
</form>
@endsection

@section('footer')
    <a href="{{ route('login') }}" class="text-primary text-decoration-none fw-semibold">Iniciar sesión</a>
@endsection
