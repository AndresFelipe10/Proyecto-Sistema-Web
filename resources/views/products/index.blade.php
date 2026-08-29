@extends('layouts.app')

@section('title', 'Catálogo de Productos')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h3 class="fw-bold mb-1">Catálogo de Productos</h3>
        <p class="text-muted small mb-0">Gestión de inventario y precios para <strong>{{ $currentBusiness->name }}</strong>.</p>
    </div>
    @can('create', App\Models\Product::class)
        <a href="{{ route('products.create') }}" class="btn btn-primary rounded-pill px-4 fw-semibold">
            <i class="bi bi-plus-circle-fill me-1"></i> Registrar Producto
        </a>
    @endcan
</div>

<!-- Filtros de Búsqueda -->
<div class="card card-custom p-3 bg-white mb-4">
    <form method="GET" action="{{ route('products.index') }}" class="row g-2 align-items-center">
        <div class="col-md-4">
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" 
                       name="search" 
                       class="form-control border-start-0 ps-0" 
                       placeholder="Buscar por SKU, nombre..." 
                       value="{{ $search }}">
            </div>
        </div>

        <div class="col-md-3">
            <select name="category_id" class="form-select">
                <option value="">Todas las categorías</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat->id }}" {{ $selectedCategory == $cat->id ? 'selected' : '' }}>
                        {{ $cat->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="col-md-2">
            <select name="status" class="form-select">
                <option value="">Todos los estados</option>
                <option value="1" {{ $selectedStatus === '1' ? 'selected' : '' }}>Activos</option>
                <option value="0" {{ $selectedStatus === '0' ? 'selected' : '' }}>Inactivos</option>
            </select>
        </div>

        <div class="col-md-2">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="low_stock" value="1" id="lowStockCheck" {{ $lowStockOnly ? 'checked' : '' }}>
                <label class="form-check-label small fw-semibold text-danger" for="lowStockCheck">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i> Stock Bajo
                </label>
            </div>
        </div>

        <div class="col-md-1 text-end">
            <button type="submit" class="btn btn-outline-primary rounded-pill w-100">Filtrar</button>
        </div>
    </form>
</div>

<!-- Tabla de Productos -->
<div class="card card-custom p-0 bg-white overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr class="small text-uppercase text-muted">
                    <th class="ps-4">SKU</th>
                    <th>Producto</th>
                    <th>Categoría</th>
                    <th>Precio Compra</th>
                    <th>Precio Venta</th>
                    <th>Stock</th>
                    <th>Estado</th>
                    <th class="text-end pe-4">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($products as $product)
                    @php
                        $isLowStock = $product->stock <= $product->min_stock;
                    @endphp
                    <tr>
                        <td class="ps-4">
                            <span class="badge bg-light text-dark font-monospace border px-2.5 py-1.5">
                                {{ $product->sku }}
                            </span>
                        </td>
                        <td>
                            <div class="fw-semibold text-dark">{{ $product->name }}</div>
                            @if ($product->description)
                                <div class="text-muted small text-truncate" style="max-width: 250px;">{{ $product->description }}</div>
                            @endif
                        </td>
                        <td>
                            @if ($product->category)
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">
                                    {{ $product->category->name }}
                                </span>
                            @else
                                <span class="text-muted small">Sin categoría</span>
                            @endif
                        </td>
                        <td class="text-secondary small">
                            ${{ number_format($product->cost_price, 2) }}
                        </td>
                        <td class="fw-bold text-success">
                            ${{ number_format($product->sale_price, 2) }}
                        </td>
                        <td>
                            @if ($isLowStock)
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1 rounded-pill" title="Stock mínimo: {{ $product->min_stock }}">
                                    <i class="bi bi-exclamation-circle-fill me-1"></i> {{ $product->stock }} unid.
                                </span>
                            @else
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 rounded-pill">
                                    <i class="bi bi-check-circle-fill me-1"></i> {{ $product->stock }} unid.
                                </span>
                            @endif
                        </td>
                        <td>
                            @if ($product->is_active)
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1">
                                    Activo
                                </span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2.5 py-1">
                                    Inactivo
                                </span>
                            @endif
                        </td>
                        <td class="text-end pe-4">
                            <div class="d-inline-flex gap-1">
                                <a href="{{ route('products.show', $product) }}" class="btn btn-light btn-sm rounded-pill px-2.5 text-secondary" title="Detalle">
                                    <i class="bi bi-eye"></i>
                                </a>

                                @can('update', $product)
                                    <a href="{{ route('products.edit', $product) }}" class="btn btn-outline-secondary btn-sm rounded-pill px-2.5" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                @endcan

                                @can('delete', $product)
                                    <form method="POST" action="{{ route('products.destroy', $product) }}" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill px-2.5" onclick="return confirm('¿Estás seguro de eliminar este producto?')" title="Eliminar">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-box-seam display-4 d-block mb-2 text-secondary opacity-50"></i>
                            No se encontraron productos con los filtros seleccionados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($products->hasPages())
        <div class="p-3 border-top">
            {{ $products->links() }}
        </div>
    @endif
</div>
@endsection
