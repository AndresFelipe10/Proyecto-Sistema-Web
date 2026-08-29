@extends('layouts.app')

@section('title', 'Categorías de Productos')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h3 class="fw-bold mb-1">Categorías de Productos</h3>
        <p class="text-muted small mb-0">Organiza los productos de <strong>{{ $currentBusiness->name }}</strong> en categorías.</p>
    </div>
    @can('create', App\Models\Category::class)
        <a href="{{ route('categories.create') }}" class="btn btn-primary rounded-pill px-4 fw-semibold">
            <i class="bi bi-plus-circle-fill me-1"></i> Nueva Categoría
        </a>
    @endcan
</div>

<div class="card card-custom p-3 bg-white mb-4">
    <form method="GET" action="{{ route('categories.index') }}" class="row g-2 align-items-center">
        <div class="col-md-5">
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" 
                       name="search" 
                       class="form-control border-start-0 ps-0" 
                       placeholder="Buscar por nombre o descripción..." 
                       value="{{ $search }}">
            </div>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-outline-primary rounded-pill px-3">Buscar</button>
            @if ($search)
                <a href="{{ route('categories.index') }}" class="btn btn-light rounded-pill px-3 text-secondary">Limpiar</a>
            @endif
        </div>
    </form>
</div>

<div class="card card-custom p-0 bg-white overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr class="small text-uppercase text-muted">
                    <th class="ps-4">Nombre</th>
                    <th>Descripción</th>
                    <th>Productos Asignados</th>
                    <th>Estado</th>
                    <th class="text-end pe-4">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categories as $category)
                    <tr>
                        <td class="ps-4 fw-semibold">
                            <div class="d-flex align-items-center gap-2">
                                <span class="p-2 bg-primary-subtle text-primary rounded-3">
                                    <i class="bi bi-tag-fill"></i>
                                </span>
                                <span>{{ $category->name }}</span>
                            </div>
                        </td>
                        <td class="text-secondary small">
                            {{ $category->description ?? '—' }}
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border px-2.5 py-1.5 rounded-pill">
                                <i class="bi bi-box-seam me-1 text-primary"></i> {{ $category->products_count }} productos
                            </span>
                        </td>
                        <td>
                            @if ($category->is_active)
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1">
                                    <i class="bi bi-check-circle-fill me-1"></i> Activa
                                </span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2.5 py-1">
                                    <i class="bi bi-dash-circle-fill me-1"></i> Inactiva
                                </span>
                            @endif
                        </td>
                        <td class="text-end pe-4">
                            <div class="d-inline-flex gap-2">
                                @can('update', $category)
                                    <a href="{{ route('categories.edit', $category) }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3" title="Editar">
                                        <i class="bi bi-pencil-fill me-1"></i> Editar
                                    </a>
                                @endcan

                                @can('delete', $category)
                                    <form method="POST" action="{{ route('categories.destroy', $category) }}" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill px-3" onclick="return confirm('¿Estás seguro de eliminar esta categoría?')">
                                            <i class="bi bi-trash-fill me-1"></i> Eliminar
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            <i class="bi bi-tags display-4 d-block mb-2 text-secondary opacity-50"></i>
                            No se encontraron categorías registradas.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($categories->hasPages())
        <div class="p-3 border-top">
            {{ $categories->links() }}
        </div>
    @endif
</div>
@endsection
