@extends('layouts.app')

@section('title', 'Detalle de Receta')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h3 class="fw-bold mb-1">{{ $recipe->name }}</h3>
                <p class="text-muted small mb-0">Fórmula de preparación e insumos para <strong>{{ $recipe->product?->name }}</strong>.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('recipes.index') }}" class="btn btn-outline-secondary rounded-pill px-3">
                    <i class="bi bi-arrow-left me-1"></i> Volver
                </a>
                @can('update', $recipe)
                    <a href="{{ route('recipes.edit', $recipe) }}" class="btn btn-primary rounded-pill px-3">
                        <i class="bi bi-pencil me-1"></i> Editar Receta
                    </a>
                @endcan
            </div>
        </div>

        <div class="card card-custom p-4 bg-white mb-4">
            <h5 class="fw-bold text-primary mb-3"><i class="bi bi-info-circle me-2"></i>Información General del Plato</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <span class="text-muted small d-block">Plato Vendido</span>
                    <span class="fs-5 fw-bold text-dark">🍲 {{ $recipe->product?->name }}</span>
                    <div class="small text-muted font-monospace mt-1">SKU: {{ $recipe->product?->sku }}</div>
                </div>
                <div class="col-md-3">
                    <span class="text-muted small d-block">Precio de Venta</span>
                    <span class="fs-5 fw-bold text-dark">${{ number_format($recipe->product?->sale_price ?? 0, 2) }}</span>
                </div>
                <div class="col-md-3">
                    <span class="text-muted small d-block">Estado de la Receta</span>
                    @if ($recipe->is_active)
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 rounded-pill mt-1">
                            <i class="bi bi-check-circle-fill me-1"></i>Activa
                        </span>
                    @else
                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2.5 py-1 rounded-pill mt-1">
                            Inactiva
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <div class="card card-custom p-4 bg-white">
            <h5 class="fw-bold text-primary mb-3"><i class="bi bi-basket me-2"></i>Insumos Consumidos por Porción</h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-secondary small text-uppercase" style="font-size: 0.75rem;">
                        <tr>
                            <th class="ps-3">Insumo</th>
                            <th>Cantidad por Porción</th>
                            <th>Unidad Base</th>
                            <th>Stock Actual Disponible</th>
                            <th class="text-end pe-3">Porciones Teóricas</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recipe->items as $item)
                            @php
                                $stock = (float) ($item->ingredient?->stock ?? 0);
                                $qty = (float) $item->quantity_per_portion;
                                $portionsPossible = $qty > 0 ? floor($stock / $qty) : 0;
                            @endphp
                            <tr>
                                <td class="ps-3 fw-semibold text-dark">
                                    🥩 {{ $item->ingredient?->name ?? 'Insumo no encontrado' }}
                                    <div class="small text-muted font-monospace">SKU: {{ $item->ingredient?->sku ?? '—' }}</div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border px-2.5 py-1 fs-6 fw-bold">
                                        {{ (float) $item->quantity_per_portion == (int) $item->quantity_per_portion ? (int) $item->quantity_per_portion : $item->quantity_per_portion }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                        {{ $item->unit }}
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-semibold {{ $stock <= 0 ? 'text-danger' : 'text-dark' }}">
                                        {{ $item->ingredient?->formatted_stock ?? 0 }} {{ $item->unit }}
                                    </span>
                                </td>
                                <td class="text-end pe-3">
                                    @if ($portionsPossible <= 0)
                                        <span class="badge bg-danger text-white rounded-pill px-2.5 py-1">
                                            Agotado (0 platos)
                                        </span>
                                    @elseif ($portionsPossible <= 5)
                                        <span class="badge bg-warning text-dark border border-warning rounded-pill px-2.5 py-1">
                                            {{ $portionsPossible }} platos restantes
                                        </span>
                                    @else
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1">
                                            {{ $portionsPossible }} platos
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    Esta receta no tiene ingredientes registrados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
