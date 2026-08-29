@extends('layouts.app')

@section('title', 'Registrar Producto')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card card-custom p-4 p-md-5 bg-white">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-1">Registrar Producto</h3>
                    <p class="text-muted small mb-0">Agrega un nuevo artículo al catálogo de <strong>{{ $currentBusiness->name }}</strong>.</p>
                </div>
                <a href="{{ route('products.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                    <i class="bi bi-arrow-left me-1"></i> Volver
                </a>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger mb-4 py-2 px-3 small rounded-3">
                    <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> Corrige los siguientes errores:</div>
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('products.store') }}" novalidate>
                @csrf

                <div class="row g-3 mb-3">
                    <div class="col-md-8">
                        <label for="name" class="form-label small fw-semibold text-secondary">Nombre del producto <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control @error('name') is-invalid @enderror" 
                               id="name" 
                               name="name" 
                               value="{{ old('name') }}" 
                               placeholder="Ej. Zapatillas Running Cali Pro" 
                               required 
                               autofocus>
                    </div>

                    <div class="col-md-4">
                        <label for="sku" class="form-label small fw-semibold text-secondary">Código / SKU <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control font-monospace @error('sku') is-invalid @enderror" 
                               id="sku" 
                               name="sku" 
                               value="{{ old('sku') }}" 
                               placeholder="Ej. ZAP-001" 
                               required>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="category_id" class="form-label small fw-semibold text-secondary">Categoría (Opcional)</label>
                        <select class="form-select @error('category_id') is-invalid @enderror" id="category_id" name="category_id">
                            <option value="">Sin categoría</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="cost_price" class="form-label small fw-semibold text-secondary">Precio de Compra <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" 
                                   step="0.01" 
                                   min="0" 
                                   class="form-control @error('cost_price') is-invalid @enderror" 
                                   id="cost_price" 
                                   name="cost_price" 
                                   value="{{ old('cost_price', '0.00') }}" 
                                   required>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label for="sale_price" class="form-label small fw-semibold text-secondary">Precio de Venta <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" 
                                   step="0.01" 
                                   min="0" 
                                   class="form-control @error('sale_price') is-invalid @enderror" 
                                   id="sale_price" 
                                   name="sale_price" 
                                   value="{{ old('sale_price', '0.00') }}" 
                                   required>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="stock" class="form-label small fw-semibold text-secondary">Stock Inicial <span class="text-danger">*</span></label>
                        <input type="number" 
                               min="0" 
                               class="form-control @error('stock') is-invalid @enderror" 
                               id="stock" 
                               name="stock" 
                               value="{{ old('stock', '0') }}" 
                               required>
                    </div>

                    <div class="col-md-6">
                        <label for="min_stock" class="form-label small fw-semibold text-secondary">Stock Mínimo (Alerta de reposición) <span class="text-danger">*</span></label>
                        <input type="number" 
                               min="0" 
                               class="form-control @error('min_stock') is-invalid @enderror" 
                               id="min_stock" 
                               name="min_stock" 
                               value="{{ old('min_stock', '5') }}" 
                               required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label small fw-semibold text-secondary">Descripción del Producto (Opcional)</label>
                    <textarea class="form-control @error('description') is-invalid @enderror" 
                              id="description" 
                              name="description" 
                              rows="3" 
                              placeholder="Tallas, colores, material u otras características relevantes...">{{ old('description') }}</textarea>
                </div>

                <div class="mb-4 form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" {{ old('is_active', '1') == '1' ? 'checked' : '' }}>
                    <label class="form-check-label small fw-semibold text-secondary" for="is_active">Producto activo y disponible para venta</label>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('products.index') }}" class="btn btn-outline-secondary px-4 rounded-3">Cancelar</a>
                    <button type="submit" class="btn btn-primary px-4 rounded-3 fw-semibold">
                        <i class="bi bi-check-circle-fill me-1"></i> Guardar Producto
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
