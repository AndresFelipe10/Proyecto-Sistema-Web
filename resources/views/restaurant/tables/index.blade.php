@extends('layouts.app')

@section('title', 'Salón y Mapa de Mesas')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark">
                <i class="bi bi-grid-3x3-gap-fill text-primary me-2"></i>Salón y Mapa de Mesas
            </h1>
            <p class="text-muted small mb-0">Gestión de mesas en tiempo real y asignación rápida de comandas.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('restaurant.orders.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-receipt me-1"></i> Ver Comandas
            </a>
            @can('create', App\Models\RestaurantTable::class)
                <a href="{{ route('restaurant.tables.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-1"></i> Nueva Mesa
                </a>
            @endcan
        </div>
    </div>

    {{-- Indicadores de estado del salón --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-secondary bg-opacity-10 p-3 me-3 text-secondary">
                        <i class="bi bi-grid fs-4"></i>
                    </div>
                    <div>
                        <span class="text-muted small fw-semibold">Total Mesas</span>
                        <h4 class="fw-bold mb-0">{{ $stats['total'] }}</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3 text-success">
                        <i class="bi bi-check-circle-fill fs-4"></i>
                    </div>
                    <div>
                        <span class="text-muted small fw-semibold">Libres</span>
                        <h4 class="fw-bold mb-0 text-success">{{ $stats['available'] }}</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3 text-primary">
                        <i class="bi bi-people-fill fs-4"></i>
                    </div>
                    <div>
                        <span class="text-muted small fw-semibold">Ocupadas</span>
                        <h4 class="fw-bold mb-0 text-primary">{{ $stats['occupied'] }}</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3 text-warning">
                        <i class="bi bi-hourglass-split fs-4"></i>
                    </div>
                    <div>
                        <span class="text-muted small fw-semibold">En Cobro</span>
                        <h4 class="fw-bold mb-0 text-warning">{{ $stats['billed'] }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Grid de Mesas --}}
    @if($tables->isEmpty())
        <div class="card border-0 shadow-sm rounded-4 text-center py-5">
            <div class="card-body">
                <i class="bi bi-grid-3x3 text-muted display-4 mb-3"></i>
                <h5 class="fw-bold">No hay mesas configuradas aún</h5>
                <p class="text-muted">Crea las mesas de tu salón, barra o terraza para comenzar a tomar comandas.</p>
                @can('create', App\Models\RestaurantTable::class)
                    <a href="{{ route('restaurant.tables.create') }}" class="btn btn-primary mt-2">
                        <i class="bi bi-plus-circle me-1"></i> Crear Primera Mesa
                    </a>
                @endcan
            </div>
        </div>
    @else
        <div class="row g-4">
            @foreach($tables as $table)
                @php
                    $isAvailable = $table->status === 'available';
                    $isOccupied = $table->status === 'occupied';
                    $isBilled = $table->status === 'billed';
                    $activeOrder = $table->activeOrder;
                @endphp
                <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
                    <div class="card h-100 shadow-sm rounded-4 border-2 transition-all {{ $isAvailable ? 'border-success' : ($isOccupied ? 'border-primary' : 'border-warning') }}">
                        <div class="card-header bg-white border-0 pt-3 pb-2 d-flex justify-content-between align-items-center">
                            <span class="badge rounded-pill px-3 py-1 fw-bold {{ $isAvailable ? 'bg-success text-white' : ($isOccupied ? 'bg-primary text-white' : 'bg-warning text-dark') }}">
                                {{ $isAvailable ? 'LIBRE' : ($isOccupied ? 'OCUPADA' : 'EN COBRO') }}
                            </span>

                            @can('update', $table)
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light rounded-circle text-muted" type="button" data-bs-toggle="dropdown">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                        <li>
                                            <a class="dropdown-item py-2" href="{{ route('restaurant.tables.edit', $table) }}">
                                                <i class="bi bi-pencil me-2 text-primary"></i> Editar Mesa
                                            </a>
                                        </li>
                                        @if(!$isOccupied)
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form action="{{ route('restaurant.tables.destroy', $table) }}" method="POST" onsubmit="return confirm('¿Seguro que deseas eliminar esta mesa?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger py-2">
                                                        <i class="bi bi-trash me-2"></i> Eliminar
                                                    </button>
                                                </form>
                                            </li>
                                        @endif
                                    </ul>
                                </div>
                            @endcan
                        </div>

                        <div class="card-body text-center py-3">
                            <div class="mb-2">
                                <i class="bi bi-aspect-ratio fs-1 {{ $isAvailable ? 'text-success' : ($isOccupied ? 'text-primary' : 'text-warning') }}"></i>
                            </div>
                            <h4 class="fw-bold text-dark mb-1">{{ $table->name }}</h4>
                            <p class="text-muted small mb-3">
                                <i class="bi bi-person me-1"></i>Capacidad: {{ $table->capacity }} personas
                            </p>

                            @if($isOccupied && $activeOrder)
                                <div class="bg-light rounded-3 p-2 mb-3 text-start small">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="text-muted">Comanda:</span>
                                        <span class="fw-bold text-dark">{{ $activeOrder->order_number }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="text-muted">Mesero:</span>
                                        <span class="fw-semibold text-truncate" style="max-width: 120px;">{{ $activeOrder->user->name ?? 'N/A' }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="text-muted">Tiempo:</span>
                                        <span class="text-secondary fw-semibold">{{ $activeOrder->created_at->diffForHumans(null, true) }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between border-top pt-1 mt-1">
                                        <span class="fw-bold text-dark">Total:</span>
                                        <span class="fw-bold text-primary">${{ number_format($activeOrder->total, 2) }}</span>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="card-footer bg-white border-0 pt-0 pb-3">
                            @if($isAvailable)
                                <a href="{{ route('restaurant.orders.create', ['table_id' => $table->id]) }}" class="btn btn-outline-success w-100 fw-bold py-2">
                                    <i class="bi bi-plus-lg me-1"></i> Abrir Mesa
                                </a>
                            @elseif($activeOrder)
                                <a href="{{ route('restaurant.orders.show', $activeOrder) }}" class="btn btn-primary w-100 fw-bold py-2">
                                    <i class="bi bi-receipt me-1"></i> Ver / Editar Comanda
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
