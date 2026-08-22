@extends('layouts.auth')

@section('title', 'Recuperar Contraseña')
@section('heading', 'Recuperar acceso')
@section('subheading', 'Ingresa tu correo y te enviaremos las instrucciones de restablecimiento')

@section('content')
<form method="POST" action="{{ route('password.email') }}" novalidate>
    @csrf

    <div class="mb-4">
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
                   autofocus>
        </div>
    </div>

    <button type="submit" class="btn btn-primary-custom mb-3">
        <i class="bi bi-send-fill me-1"></i> Enviar Enlace de Recuperación
    </button>

    <div class="text-center">
        <a href="{{ route('login') }}" class="small text-secondary text-decoration-none">
            <i class="bi bi-arrow-left me-1"></i> Regresar al inicio de sesión
        </a>
    </div>
</form>
@endsection

@section('footer')
    ¿Recordaste tu clave? 
    <a href="{{ route('login') }}" class="text-primary text-decoration-none fw-semibold">Iniciar sesión</a>
@endsection
