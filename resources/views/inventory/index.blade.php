@extends('layouts.app')

@section('title', 'Control de Inventario')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h3 class="fw-bold mb-1">Historial de Movimientos de Inventario</h3>
        <p class="text-muted small mb-0">Trazabilidad y control de existencias en <strong>{{ $currentBusiness->name }}</strong>.</p>
    </div>
    @can('create', App\Models\InventoryMovement::class)
        <a href="{{ route('inventory.create') }}" class="btn btn-primary rounded-pill px-4 fw-semibold">
            <i class="bi bi-plus-circle-fill me-1"></i> Registrar Movimiento
        </a>
    @endcan
</div>

<!-- Filtros de Historial -->
<div class="card card-custom p-3 bg-white mb-4">
    <form method="GET" action="{{ route('inventory.index') }}" class="row g-2 align-items-center">
        <div class="col-md-4">
            <select name="product_id" class="form-select">
                <option value="">Todos los productos</option>
                @foreach ($products as $prod)
                    <option value="{{ $prod->id }}" {{ $selectedProduct == $prod->id ? 'selected' : '' }}>
                        {{ $prod->name }} (SKU: {{ $prod->sku }})
                    </option>
                @endforeach
            </select>
        </div>

        <div class="col-md-2">
            <select name="type" class="form-select">
                <option value="">Todos los tipos</option>
                <option value="entry" {{ $selectedType === 'entry' ? 'selected' : '' }}>Entrada (+)</option>
                <option value="exit" {{ $selectedType === 'exit' ? 'selected' : '' }}>Salida (-)</option>
                <option value="adjustment" {{ $selectedType === 'adjustment' ? 'selected' : '' }}>Ajuste (~)</option>
            </select>
        </div>

        <div class="col-md-2">
            <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}" placeholder="Desde">
        </div>

        <div class="col-md-2">
            <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}" placeholder="Hasta">
        </div>

        <div class="col-md-2 text-end d-flex gap-2">
            <button type="submit" class="btn btn-outline-primary rounded-pill flex-grow-1">Filtrar</button>
            @if ($selectedProduct || $selectedType || $dateFrom || $dateTo)
                <a href="{{ route('inventory.index') }}" class="btn btn-light rounded-pill text-secondary" title="Limpiar"><i class="bi bi-x-lg"></i></a>
            @endif
        </div>
    </form>
</div>

<!-- Tabla de Movimientos -->
<div class="card card-custom p-0 bg-white overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr class="small text-uppercase text-muted">
                    <th class="ps-4">Fecha y Hora</th>
                    <th>Producto</th>
                    <th>Tipo</th>
                    <th>Cantidad</th>
                    <th>Variación de Stock</th>
                    <th>Motivo</th>
                    <th class="pe-4">Responsable</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($movements as $movement)
                    <tr>
                        <td class="ps-4 small text-secondary">
                            {{ $movement->movement_date ? $movement->movement_date->format('d/m/Y H:i') : $movement->created_at->format('d/m/Y H:i') }}
                        </td>
                        <td>
                            <div class="fw-semibold text-dark">{{ $movement->product->name }}</div>
                            <span class="badge bg-light text-dark font-monospace border small">
                                SKU: {{ $movement->product->sku }}
                            </span>
                        </td>
                        <td>
                            @if ($movement->type === 'entry')
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 rounded-pill">
                                    <i class="bi bi-arrow-down-left me-1"></i> Entrada
                                </span>
                            @elseif ($movement->type === 'exit')
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1 rounded-pill">
                                    <i class="bi bi-arrow-up-right me-1"></i> Salida
                                </span>
                            @else
                                <span class="badge bg-info-subtle text-info border border-info-subtle px-2.5 py-1 rounded-pill">
                                    <i class="bi bi-sliders me-1"></i> Ajuste
                                </span>
                            @endif
                        </td>
                        <td class="fw-bold">
                            @if ($movement->type === 'entry')
                                <span class="text-success">+{{ $movement->quantity }}</span>
                            @elseif ($movement->type === 'exit')
                                <span class="text-danger">-{{ $movement->quantity }}</span>
                            @else
                                <span class="text-info">{{ $movement->quantity }}</span>
                            @endif
                        </td>
                        <td>
                            <span class="text-muted small">{{ $movement->previous_stock }}</span>
                            <i class="bi bi-arrow-right mx-1 text-secondary"></i>
                            <span class="fw-bold text-dark">{{ $movement->new_stock }} unid.</span>
                        </td>
                        <td class="small text-secondary" style="max-width: 250px;">
                            {{ $movement->reason ?? 'Sin motivo especificado' }}
                            @if ($movement->sale_id)
                                <span class="badge bg-light text-primary border ms-1">Venta #{{ $movement->sale_id }}</span>
                            @endif
                        </td>
                        <td class="pe-4 small text-secondary">
                            <i class="bi bi-person-fill me-1 text-primary"></i> {{ $movement->user->name ?? 'Sistema' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-clock-history display-4 d-block mb-2 text-secondary opacity-50"></i>
                            No se encontraron movimientos de inventario registrados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($movements->hasPages())
        <div class="p-3 border-top">
            {{ $movements->links() }}
        </div>
    @endif
</div>
@endsection
