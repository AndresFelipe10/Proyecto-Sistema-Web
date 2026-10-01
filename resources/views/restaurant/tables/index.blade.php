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
            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#kitchenConfigModal" title="Configuración de Comandas">
                <i class="bi bi-gear-fill me-1"></i> Configuración
            </button>
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
        <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-xl-6 g-2 g-md-3">
            @foreach($tables as $table)
                @php
                    $isAvailable = $table->status === 'available';
                    $isOccupied = $table->status === 'occupied';
                    $isBilled = $table->status === 'billed';
                    $activeOrder = $table->activeOrder;
                @endphp
                <div class="col">
                    <div class="card h-100 shadow-sm rounded-3 border-2 transition-all {{ $isAvailable ? 'border-success' : ($isOccupied ? 'border-primary' : 'border-warning') }}">
                        <div class="card-header bg-white border-0 p-2 d-flex justify-content-between align-items-center">
                            <span class="badge px-2 py-1 fw-bold {{ $isAvailable ? 'bg-success text-white' : ($isOccupied ? 'bg-primary text-white' : 'bg-warning text-dark') }}" style="font-size: 0.68rem;">
                                {{ $isAvailable ? 'LIBRE' : ($isOccupied ? 'OCUPADA' : 'EN COBRO') }}
                            </span>

                            <div class="d-flex align-items-center gap-1">
                                <span class="badge bg-light text-secondary border small px-1.5 py-0.5" title="Capacidad: {{ $table->capacity }} personas" style="font-size: 0.68rem;">
                                    <i class="bi bi-person"></i>{{ $table->capacity }}
                                </span>

                                @can('update', $table)
                                    <div class="dropdown">
                                        <button class="btn btn-xs btn-light rounded-circle text-muted p-0 d-flex align-items-center justify-content-center" style="width: 24px; height: 24px;" type="button" data-bs-toggle="dropdown" aria-label="Opciones de mesa">
                                            <i class="bi bi-three-dots-vertical" style="font-size: 0.75rem;"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                            <li>
                                                <a class="dropdown-item py-1.5 small" href="{{ route('restaurant.tables.edit', $table) }}">
                                                    <i class="bi bi-pencil me-1.5 text-primary"></i> Editar Mesa
                                                </a>
                                            </li>
                                            @if(!$isOccupied && !$isBilled)
                                                <li><hr class="dropdown-divider my-1"></li>
                                                <li>
                                                    <form action="{{ route('restaurant.tables.destroy', $table) }}" method="POST" onsubmit="return confirm('¿Seguro que deseas eliminar esta mesa?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="dropdown-item text-danger py-1.5 small">
                                                            <i class="bi bi-trash me-1.5"></i> Eliminar
                                                        </button>
                                                    </form>
                                                </li>
                                            @endif
                                        </ul>
                                    </div>
                                @endcan
                            </div>
                        </div>

                        <div class="card-body text-center p-2 d-flex flex-column justify-content-between">
                            <div class="mb-1">
                                <i class="bi bi-aspect-ratio fs-4 {{ $isAvailable ? 'text-success' : ($isOccupied ? 'text-primary' : 'text-warning') }}"></i>
                                <h6 class="fw-bold text-dark mb-0 text-truncate px-1" title="{{ $table->name }}">{{ $table->name }}</h6>
                            </div>

                            @if(($isOccupied || $isBilled) && $activeOrder)
                                <div class="bg-light rounded-2 p-1.5 mb-1 text-start small border" style="font-size: 0.75rem;">
                                    <div class="d-flex justify-content-between align-items-center mb-0.5">
                                        <span class="fw-bold text-dark text-truncate" style="max-width: 65px;" title="{{ $activeOrder->order_number }}">#{{ $activeOrder->order_number }}</span>
                                        <span class="fw-bold text-primary">${{ number_format($activeOrder->total, 0, ',', '.') }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center text-muted" style="font-size: 0.7rem;">
                                        <span class="text-truncate" style="max-width: 65px;" title="{{ !empty($activeOrder->customer_name) ? $activeOrder->customer_name : ($activeOrder->user->name ?? 'Usuario') }}">
                                            <i class="bi bi-person-badge"></i> {{ !empty($activeOrder->customer_name) ? $activeOrder->customer_name : ($activeOrder->user->name ?? 'Usuario') }}
                                        </span>
                                        <span class="text-secondary fw-semibold">
                                            <i class="bi bi-clock"></i> {{ $activeOrder->created_at->diffForHumans(null, true) }}
                                        </span>
                                    </div>
                                </div>
                            @else
                                <div class="py-1 text-muted small" style="font-size: 0.72rem;">
                                    <span class="text-success"><i class="bi bi-check2"></i> Disponible</span>
                                </div>
                            @endif
                        </div>

                        <div class="card-footer bg-white border-0 p-2 pt-0">
                            @if($isAvailable)
                                <a href="{{ route('restaurant.orders.create', ['table_id' => $table->id]) }}" class="btn btn-sm btn-outline-success w-100 fw-bold d-flex align-items-center justify-content-center py-2" style="min-height: 38px; font-size: 0.8rem;">
                                    <i class="bi bi-plus-lg me-1"></i> + Abrir Mesa
                                </a>
                            @elseif($activeOrder)
                                <a href="{{ route('restaurant.orders.show', $activeOrder) }}" class="btn btn-sm btn-primary w-100 fw-bold d-flex align-items-center justify-content-center py-2" style="min-height: 38px; font-size: 0.8rem;">
                                    <i class="bi bi-receipt me-1"></i> Ver Comanda
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

@include('restaurant.partials.kitchen-config-modal')
@endsection
