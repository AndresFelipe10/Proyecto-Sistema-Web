@extends('layouts.app')

@php
    $isRestaurant = app(\App\Services\Tenant\TenantManager::class)->get()?->isRestaurant();
@endphp

@section('title', $isRestaurant ? 'Editar Plato / Producto del Menú' : 'Editar Producto')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card card-custom p-4 p-md-5 bg-white">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-1">
                        {{ $isRestaurant ? 'Editar Plato / Producto del Menú' : 'Editar Producto' }}
                    </h3>
                    <p class="text-muted small mb-0">Modifica los datos y precios de <strong>{{ $product->name }}</strong>.</p>
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

            <form method="POST" action="{{ route('products.update', $product) }}" novalidate>
                @csrf
                @method('PUT')

                @if ($isRestaurant)
                    {{-- Formulario para Restaurante --}}
                    <input type="hidden" name="base_unit" value="{{ $product->base_unit ?? 'unit' }}">

                    <div class="mb-4 p-3 bg-light rounded-3 border">
                        <label class="form-label small fw-semibold text-secondary d-block mb-2">Modalidad del Artículo en Restaurante</label>
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <div class="form-check p-2 border rounded-2 bg-white h-100">
                                    <input class="form-check-input ms-1 me-2" type="radio" name="product_type" id="type_dish" value="dish" {{ old('product_type', $product->product_type ?? 'dish') === 'dish' ? 'checked' : '' }} onchange="toggleRestaurantProductType()">
                                    <label class="form-check-label fw-semibold text-dark cursor-pointer" for="type_dish">
                                        <i class="bi bi-book-half text-primary me-1"></i> Carta / Menú de Cocina
                                        <small class="d-block text-muted fw-normal">Plato o bebida preparada. Va a comanda de cocina y no requiere existencias físicas estrictas.</small>
                                    </label>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-check p-2 border rounded-2 bg-white h-100">
                                    <input class="form-check-input ms-1 me-2" type="radio" name="product_type" id="type_standard" value="standard" {{ old('product_type', $product->product_type ?? 'dish') === 'standard' ? 'checked' : '' }} onchange="toggleRestaurantProductType()">
                                    <label class="form-check-label fw-semibold text-dark cursor-pointer" for="type_standard">
                                        <i class="bi bi-boxes text-warning-emphasis me-1"></i> Mercancía de Mostrador
                                        <small class="text-muted d-block mt-1">
                                            Artículos, mercancía empacada, bebidas selladas o productos de reventa. Controla stock físico, costo de compra y no causa INC.
                                        </small>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Campos de Inventario y Costo para Mercancía de Mostrador --}}
                    <div id="merchandise_fields" class="row g-3 mb-4 p-3 bg-warning-subtle rounded-3 border border-warning-subtle" style="display: {{ old('product_type', $product->product_type ?? 'dish') === 'standard' ? 'flex' : 'none' }};">
                        <div class="col-12 mb-1">
                            <span class="small fw-bold text-dark"><i class="bi bi-box-seam me-1"></i>Control de Inventario y Costos (Mercancía)</span>
                        </div>
                        <div class="col-md-4">
                            <label for="cost_price" class="form-label small fw-semibold text-secondary">Precio de Compra / Costo <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" min="0" class="form-control @error('cost_price') is-invalid @enderror" id="cost_price" name="cost_price" value="{{ old('cost_price', $product->cost_price ?? '0.00') }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label for="stock" class="form-label small fw-semibold text-secondary">Stock Actual <span class="text-danger">*</span></label>
                            <input type="number" min="0" step="any" class="form-control @error('stock') is-invalid @enderror" id="stock" name="stock" value="{{ old('stock', $product->stock ?? '0') }}">
                        </div>
                        <div class="col-md-4">
                            <label for="min_stock" class="form-label small fw-semibold text-secondary">Stock Mínimo (Alerta) <span class="text-danger">*</span></label>
                            <input type="number" min="0" step="any" class="form-control @error('min_stock') is-invalid @enderror" id="min_stock" name="min_stock" value="{{ old('min_stock', $product->min_stock ?? '0') }}">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label for="name" class="form-label small fw-semibold text-secondary">Nombre del plato o producto <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control @error('name') is-invalid @enderror" 
                                   id="name" 
                                   name="name" 
                                   value="{{ old('name', $product->name) }}" 
                                   placeholder="Ej. Bandeja Paisa, Limonada Natural, Pizza Personal" 
                                   required 
                                   autofocus>
                        </div>

                        <div class="col-md-4">
                            <label for="sale_price" class="form-label small fw-semibold text-secondary">Precio de Venta <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" 
                                       step="0.01" 
                                       min="0" 
                                       class="form-control @error('sale_price') is-invalid @enderror" 
                                       id="sale_price" 
                                       name="sale_price" 
                                       value="{{ old('sale_price', $product->sale_price) }}" 
                                       required>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="category_id" class="form-label small fw-semibold text-secondary">Categoría del Menú (Opcional)</label>
                            <select class="form-select @error('category_id') is-invalid @enderror" id="category_id" name="category_id">
                                <option value="">-- Sin categoría / General --</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" {{ old('category_id', $product->category_id) == $category->id ? 'selected' : '' }}>
                                        {{ $category->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('category_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="sku" class="form-label small fw-semibold text-secondary">Código / Referencia <span class="text-muted">(Opcional)</span></label>
                            <input type="text" 
                                   class="form-control font-monospace @error('sku') is-invalid @enderror" 
                                   id="sku" 
                                   name="sku" 
                                   value="{{ old('sku', $product->sku) }}">
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" {{ old('is_active', $product->is_active ? '1' : '0') == '1' ? 'checked' : '' }}>
                            <label class="form-check-label small fw-semibold text-secondary" for="is_active">Disponible en carta / menú</label>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="description" class="form-label small fw-semibold text-secondary">Descripción / Acompañamientos <span class="text-muted">(Opcional)</span></label>
                        <textarea class="form-control @error('description') is-invalid @enderror" 
                                  id="description" 
                                  name="description" 
                                  rows="3" 
                                  placeholder="Detalle de ingredientes visibles, salsas, guarnición o acompañamiento...">{{ old('description', $product->description) }}</textarea>
                    </div>

                @else
                    {{-- Formulario Estándar para Comercio (Retail) --}}
                    <input type="hidden" name="product_type" value="{{ $product->product_type ?? 'standard' }}">
                    <input type="hidden" name="base_unit" value="{{ $product->base_unit ?? 'unit' }}">

                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label for="name" class="form-label small fw-semibold text-secondary">Nombre del producto <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control @error('name') is-invalid @enderror" 
                                   id="name" 
                                   name="name" 
                                   value="{{ old('name', $product->name) }}" 
                                   required 
                                   autofocus>
                        </div>

                        <div class="col-md-4">
                            <label for="sku" class="form-label small fw-semibold text-secondary">Código / SKU <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control font-monospace @error('sku') is-invalid @enderror" 
                                   id="sku" 
                                   name="sku" 
                                   value="{{ old('sku', $product->sku) }}" 
                                   required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="category_id" class="form-label small fw-semibold text-secondary">Categoría (Opcional)</label>
                            <select class="form-select @error('category_id') is-invalid @enderror" id="category_id" name="category_id">
                                <option value="">Sin categoría</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" {{ old('category_id', $product->category_id) == $category->id ? 'selected' : '' }}>
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
                                       value="{{ old('cost_price', $product->cost_price) }}" 
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
                                       value="{{ old('sale_price', $product->sale_price) }}" 
                                       required>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="stock" class="form-label small fw-semibold text-secondary">Stock Actual <span class="text-danger">*</span></label>
                            <input type="number" 
                                   min="0" 
                                   step="any"
                                   class="form-control @error('stock') is-invalid @enderror" 
                                   id="stock" 
                                   name="stock" 
                                   value="{{ old('stock', $product->stock) }}" 
                                   required>
                        </div>

                        <div class="col-md-6">
                            <label for="min_stock" class="form-label small fw-semibold text-secondary">Stock Mínimo (Alerta de reposición) <span class="text-danger">*</span></label>
                            <input type="number" 
                                   min="0" 
                                   step="any"
                                   class="form-control @error('min_stock') is-invalid @enderror" 
                                   id="min_stock" 
                                   name="min_stock" 
                                   value="{{ old('min_stock', $product->min_stock) }}" 
                                   required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label small fw-semibold text-secondary">Descripción del Producto (Opcional)</label>
                        <textarea class="form-control @error('description') is-invalid @enderror" 
                                  id="description" 
                                  name="description" 
                                  rows="3">{{ old('description', $product->description) }}</textarea>
                    </div>

                    <div class="mb-4 form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" {{ old('is_active', $product->is_active ? '1' : '0') == '1' ? 'checked' : '' }}>
                        <label class="form-check-label small fw-semibold text-secondary" for="is_active">Producto activo y disponible para venta</label>
                    </div>
                @endif

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('products.index') }}" class="btn btn-outline-secondary px-4 rounded-3">Cancelar</a>
                    <button type="submit" class="btn btn-primary px-4 rounded-3 fw-semibold">
                        <i class="bi bi-save me-1"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@if ($isRestaurant)
<script>
    function toggleRestaurantProductType() {
        const isStandard = document.getElementById('type_standard')?.checked;
        const fields = document.getElementById('merchandise_fields');
        if (fields) {
            fields.style.display = isStandard ? 'flex' : 'none';
        }
    }
</script>
@endif

