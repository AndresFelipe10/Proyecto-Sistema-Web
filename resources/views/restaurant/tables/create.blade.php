@extends('layouts.app')

@section('title', 'Nueva Mesa')

@section('content')
<div class="container py-4" style="max-width: 600px;">
    <div class="d-flex align-items-center mb-4">
        <a href="{{ route('restaurant.tables.index') }}" class="btn btn-outline-secondary btn-sm me-3">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h1 class="h3 fw-bold mb-0">Nueva Mesa de Salón</h1>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <form action="{{ route('restaurant.tables.store') }}" method="POST">
                @csrf

                <div class="mb-3">
                    <label for="name" class="form-label fw-bold">Nombre o Identificador de la Mesa <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-lg @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" placeholder="Ej: Mesa 1, Mesa 2, Barra, Terraza A" required autofocus>
                    <div class="form-text">Identificador visible de la mesa para el salón y las comandas.</div>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="capacity" class="form-label fw-semibold text-secondary">Capacidad de Personas <span class="badge bg-light text-secondary border">Opcional</span></label>
                    <input type="number" class="form-control @error('capacity') is-invalid @enderror" id="capacity" name="capacity" value="{{ old('capacity') }}" min="1" max="200" placeholder="Por defecto: 4 personas">
                    <div class="form-text">Si no se especifica, el sistema asignará 4 comensales por defecto.</div>
                    @error('capacity')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="d-flex justify-content-end gap-2 pt-2">
                    <a href="{{ route('restaurant.tables.index') }}" class="btn btn-light px-4">Cancelar</a>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">
                        <i class="bi bi-save me-1"></i> Guardar Mesa
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
