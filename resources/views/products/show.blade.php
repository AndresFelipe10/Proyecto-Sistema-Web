@extends('layouts.app')

@section('title', $product->name)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div class="d-flex align-items-center gap-3">
        <a href="{{ route('products.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            <i class="bi bi-arrow-left me-1"></i> Volver al Catálogo
        </a>
        <div>
            <h3 class="fw-bold mb-0">{{ $product->name }}</h3>
            <span class="badge bg-light text-dark font-monospace border">SKU: {{ $product->sku }}</span>
        </div>
    </div>

    @can('update', $product)
        <a href="{{ route('products.edit', $product) }}" class="btn btn-primary rounded-pill px-4 fw-semibold">
            <i class="bi bi-pencil-square me-1"></i> Editar Producto
        </a>
    @endcan
</div>

<div class="row g-4">
    <!-- Información Principal -->
    <div class="col-lg-7">
        <div class="card card-custom p-4 bg-white mb-4">
            <h5 class="fw-bold mb-3 border-bottom pb-2">Información del Artículo</h5>

            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <span class="text-muted small d-block">Categoría</span>
                    <span class="fw-semibold">
                        {{ $product->category ? $product->category->name : 'Sin categoría asignada' }}
                    </span>
                </div>
                <div class="col-sm-6">
                    <span class="text-muted small d-block">Estado</span>
                    @if ($product->is_active)
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1">
                            <i class="bi bi-check-circle-fill me-1"></i> Activo para ventas
                        </span>
                    @else
                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2.5 py-1">
                            <i class="bi bi-dash-circle-fill me-1"></i> Inactivo
                        </span>
                    @endif
                </div>
            </div>

            <div class="mb-3">
                <span class="text-muted small d-block">Descripción</span>
                <p class="mb-0 text-secondary">{{ $product->description ?: 'Sin descripción detallada.' }}</p>
            </div>
        </div>

        <!-- Precios y Márgenes -->
        <div class="card card-custom p-4 bg-white">
            <h5 class="fw-bold mb-3 border-bottom pb-2">Precios y Rentabilidad</h5>

            <div class="row g-3 text-center">
                <div class="col-sm-4">
                    <div class="p-3 bg-light rounded-3">
                        <span class="text-muted small d-block">Precio de Compra</span>
                        <span class="fs-5 fw-bold text-dark">${{ number_format($product->cost_price, 2) }}</span>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="p-3 bg-success-subtle rounded-3 border border-success-subtle">
                        <span class="text-success small d-block fw-semibold">Precio de Venta</span>
                        <span class="fs-5 fw-bold text-success">${{ number_format($product->sale_price, 2) }}</span>
                    </div>
                </div>
                <div class="col-sm-4">
                    @php
                        $margin = $product->sale_price - $product->cost_price;
                        $marginPercent = $product->cost_price > 0 ? ($margin / $product->cost_price) * 100 : 0;
                    @endphp
                    <div class="p-3 bg-primary-subtle rounded-3 border border-primary-subtle">
                        <span class="text-primary small d-block fw-semibold">Margen Bruto</span>
                        <span class="fs-5 fw-bold text-primary">${{ number_format($margin, 2) }}</span>
                        <span class="small d-block text-muted">({{ number_format($marginPercent, 1) }}%)</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Existencias y Alertas -->
    <div class="col-lg-5">
        <div class="card card-custom p-4 bg-white mb-4">
            <h5 class="fw-bold mb-3 border-bottom pb-2">Control de Existencias</h5>

            @php
                $isLowStock = $product->stock <= $product->min_stock;
            @endphp

            <div class="p-3 rounded-3 mb-3 text-center {{ $isLowStock ? 'bg-danger-subtle border border-danger-subtle text-danger' : 'bg-light text-dark' }}">
                <span class="small d-block fw-semibold">Stock Actual Disponible</span>
                <span class="display-5 fw-bold">{{ $product->stock }}</span>
                <span class="d-block small mt-1">unidades en inventario</span>
            </div>

            <div class="d-flex justify-content-between align-items-center py-2 border-top">
                <span class="text-muted small">Nivel de alerta (Stock mínimo)</span>
                <span class="fw-semibold">{{ $product->min_stock }} unidades</span>
            </div>

            <div class="d-flex justify-content-between align-items-center py-2 border-top">
                <span class="text-muted small">Estado de inventario</span>
                @if ($product->stock == 0)
                    <span class="badge bg-danger rounded-pill">Agotado</span>
                @elseif ($isLowStock)
                    <span class="badge bg-warning text-dark rounded-pill">Stock Bajo (Reponer)</span>
                @else
                    <span class="badge bg-success rounded-pill">Óptimo</span>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
