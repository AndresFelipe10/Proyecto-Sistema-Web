@extends('layouts.app')

@section('title', 'Directorio de Clientes')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h3 class="fw-bold mb-1">Directorio de Clientes</h3>
        <p class="text-muted small mb-0">Base de datos comercial de <strong>{{ $currentBusiness->name }}</strong>.</p>
    </div>
    @can('create', App\Models\Customer::class)
        <a href="{{ route('customers.create') }}" class="btn btn-primary rounded-pill px-4 fw-semibold">
            <i class="bi bi-person-plus-fill me-1"></i> Registrar Cliente
        </a>
    @endcan
</div>

<!-- Filtro de Búsqueda -->
<div class="card card-custom p-3 bg-white mb-4">
    <form method="GET" action="{{ route('customers.index') }}" class="row g-2 align-items-center">
        <div class="col-md-6">
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" 
                       name="search" 
                       class="form-control border-start-0 ps-0" 
                       placeholder="Buscar por nombre, cédula/NIT, teléfono o correo..." 
                       value="{{ $search }}">
            </div>
        </div>

        <div class="col-md-3">
            <select name="status" class="form-select">
                <option value="">Todos los estados</option>
                <option value="1" {{ $selectedStatus === '1' ? 'selected' : '' }}>Activos</option>
                <option value="0" {{ $selectedStatus === '0' ? 'selected' : '' }}>Inactivos</option>
            </select>
        </div>

        <div class="col-md-3 text-end d-flex gap-2">
            <button type="submit" class="btn btn-outline-primary rounded-pill flex-grow-1">Filtrar</button>
            @if ($search || $selectedStatus !== null)
                <a href="{{ route('customers.index') }}" class="btn btn-light rounded-pill text-secondary" title="Limpiar"><i class="bi bi-x-lg"></i></a>
            @endif
        </div>
    </form>
</div>

<!-- Tabla de Clientes -->
<div class="card card-custom p-0 bg-white overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr class="small text-uppercase text-muted">
                    <th class="ps-4">Cliente</th>
                    <th>Identificación</th>
                    <th>Contacto</th>
                    <th>Compras</th>
                    <th>Estado</th>
                    <th class="text-end pe-4">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($customers as $customer)
                    <tr>
                        <td class="ps-4">
                            <div class="fw-semibold text-dark">{{ $customer->name }}</div>
                            @if ($customer->address)
                                <div class="text-muted small"><i class="bi bi-geo-alt me-1"></i>{{ $customer->address }}</div>
                            @endif
                        </td>
                        <td>
                            @if ($customer->document ?? $customer->identification_number)
                                <span class="badge bg-light text-dark border font-monospace">{{ $customer->document ?? $customer->identification_number }}</span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td>
                            <div class="small">
                                @if ($customer->phone)
                                    <div><i class="bi bi-telephone me-1 text-secondary"></i>{{ $customer->phone }}</div>
                                @endif
                                @if ($customer->email)
                                    <div class="text-secondary"><i class="bi bi-envelope me-1"></i>{{ $customer->email }}</div>
                                @endif
                                @if (! $customer->phone && ! $customer->email)
                                    <span class="text-muted">—</span>
                                @endif
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1">
                                <i class="bi bi-receipt me-1"></i> {{ $customer->sales_count }} compras
                            </span>
                        </td>
                        <td>
                            @if ($customer->is_active)
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
                                <a href="{{ route('customers.show', $customer) }}" class="btn btn-light btn-sm rounded-pill px-2.5 text-secondary" title="Ver Detalle">
                                    <i class="bi bi-eye"></i>
                                </a>

                                @can('update', $customer)
                                    <a href="{{ route('customers.edit', $customer) }}" class="btn btn-outline-secondary btn-sm rounded-pill px-2.5" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                @endcan

                                @can('delete', $customer)
                                    <form method="POST" action="{{ route('customers.destroy', $customer) }}" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill px-2.5" onclick="return confirm('¿Estás seguro de eliminar este cliente?')" title="Eliminar">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-people display-4 d-block mb-2 text-secondary opacity-50"></i>
                            No se encontraron clientes registrados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($customers->hasPages())
        <div class="p-3 border-top">
            {{ $customers->links() }}
        </div>
    @endif
</div>
@endsection
