@extends('layouts.app')

@section('title', 'Recetas de Platos')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h3 class="fw-bold mb-1">Recetas y Fórmulas por Porción</h3>
        <p class="text-muted small mb-0">Control de insumos e ingredientes por cada plato vendido.</p>
    </div>
    @can('create', App\Models\Recipe::class)
        <a href="{{ route('recipes.create') }}" class="btn btn-primary rounded-pill px-4 fw-semibold">
            <i class="bi bi-plus-circle-fill me-1"></i> Nueva Receta
        </a>
    @endcan
</div>

@if (session('status'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>{{ session('status') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
@endif

<!-- Filtros de Búsqueda -->
<div class="card card-custom p-3 bg-white mb-4">
    <form method="GET" action="{{ route('recipes.index') }}" class="row g-2 align-items-center">
        <div class="col-md-6">
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" 
                       name="search" 
                       class="form-control border-start-0 ps-0" 
                       placeholder="Buscar por nombre de receta o plato..." 
                       value="{{ $search }}">
            </div>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-secondary w-100 fw-semibold">
                <i class="bi bi-filter me-1"></i> Filtrar
            </button>
        </div>
        @if ($search)
            <div class="col-md-2">
                <a href="{{ route('recipes.index') }}" class="btn btn-outline-secondary w-100">
                    Limpiar
                </a>
            </div>
        @endif
    </form>
</div>

<!-- Listado de Recetas -->
<div class="card card-custom bg-white">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-secondary text-uppercase small" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                <tr>
                    <th class="ps-3">Plato Asociado</th>
                    <th>Nombre de la Receta</th>
                    <th>Ingredientes (Insumos)</th>
                    <th>Estado</th>
                    <th class="text-end pe-3">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recipes as $recipe)
                    <tr>
                        <td class="ps-3">
                            <span class="fw-bold text-dark d-block">
                                🍲 {{ $recipe->product?->name ?? 'Plato no encontrado' }}
                            </span>
                            <span class="text-muted small font-monospace">
                                SKU: {{ $recipe->product?->sku ?? '—' }} | Precio: ${{ number_format($recipe->product?->sale_price ?? 0, 2) }}
                            </span>
                        </td>
                        <td class="fw-semibold text-secondary">
                            {{ $recipe->name }}
                        </td>
                        <td>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 rounded-pill">
                                <i class="bi bi-basket me-1"></i>{{ $recipe->items->count() }} {{ $recipe->items->count() === 1 ? 'insumo' : 'insumos' }}
                            </span>
                        </td>
                        <td>
                            @if ($recipe->is_active)
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 rounded-pill">
                                    <i class="bi bi-check-circle-fill me-1"></i>Activa
                                </span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2.5 py-1 rounded-pill">
                                    Inactiva
                                </span>
                            @endif
                        </td>
                        <td class="text-end pe-3">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('recipes.show', $recipe) }}" 
                                   class="btn btn-outline-secondary" 
                                   title="Ver Detalle">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @can('update', $recipe)
                                    <a href="{{ route('recipes.edit', $recipe) }}" 
                                       class="btn btn-outline-primary" 
                                       title="Editar Receta">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                @endcan
                                @can('delete', $recipe)
                                    <form method="POST" 
                                          action="{{ route('recipes.destroy', $recipe) }}" 
                                          class="d-inline"
                                          onsubmit="return confirm('¿Está seguro de eliminar la receta de {{ $recipe->name }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" 
                                                class="btn btn-outline-danger" 
                                                title="Eliminar">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            <i class="bi bi-journal-x fs-1 d-block mb-3 text-secondary opacity-50"></i>
                            <h6 class="fw-bold">No se encontraron recetas</h6>
                            <p class="small mb-3">Define los insumos y porciones que componen tus platos para descontar inventario automáticamente.</p>
                            @can('create', App\Models\Recipe::class)
                                <a href="{{ route('recipes.create') }}" class="btn btn-primary btn-sm rounded-pill px-3">
                                    <i class="bi bi-plus-circle me-1"></i> Crear primera receta
                                </a>
                            @endcan
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($recipes->hasPages())
        <div class="card-footer bg-white border-0 py-3">
            {{ $recipes->links() }}
        </div>
    @endif
</div>
@endsection
