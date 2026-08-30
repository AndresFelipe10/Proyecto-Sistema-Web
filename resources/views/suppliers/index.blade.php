@extends('layouts.app')

@section('title', 'Directorio de Proveedores')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h3 class="fw-bold mb-1">Directorio de Proveedores</h3>
        <p class="text-muted small mb-0">Gestión de proveedores y contactos de suministro para <strong>{{ $currentBusiness->name }}</strong>.</p>
    </div>
    @can('create', App\Models\Supplier::class)
        <a href="{{ route('suppliers.create') }}" class="btn btn-primary rounded-pill px-4 fw-semibold">
            <i class="bi bi-truck me-1"></i> Registrar Proveedor
        </a>
    @endcan
</div>

<!-- Filtro de Búsqueda -->
<div class="card card-custom p-3 bg-white mb-4">
    <form method="GET" action="{{ route('suppliers.index') }}" class="row g-2 align-items-center">
        <div class="col-md-6">
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" 
                       name="search" 
                       class="form-control border-start-0 ps-0" 
                       placeholder="Buscar por proveedor, contacto, NIT o teléfono..." 
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
                <a href="{{ route('suppliers.index') }}" class="btn btn-light rounded-pill text-secondary" title="Limpiar"><i class="bi bi-x-lg"></i></a>
            @endif
        </div>
    </form>
</div>

<!-- Tabla de Proveedores -->
<div class="card card-custom p-0 bg-white overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr class="small text-uppercase text-muted">
                    <th class="ps-4">Proveedor / Empresa</th>
                    <th>NIT / Identificación</th>
                    <th>Persona de Contacto</th>
                    <th>Contacto</th>
                    <th>Estado</th>
                    <th class="text-end pe-4">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($suppliers as $supplier)
                    <tr>
                        <td class="ps-4">
                            <div class="fw-semibold text-dark">{{ $supplier->name }}</div>
                            @if ($supplier->address)
                                <div class="text-muted small"><i class="bi bi-geo-alt me-1"></i>{{ $supplier->address }}</div>
                            @endif
                        </td>
                        <td>
                            @if ($supplier->identification_number)
                                <span class="badge bg-light text-dark border font-monospace">{{ $supplier->identification_number }}</span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($supplier->contact_name)
                                <div><i class="bi bi-person me-1 text-primary"></i>{{ $supplier->contact_name }}</div>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td>
                            <div class="small">
                                @if ($supplier->phone)
                                    <div><i class="bi bi-telephone me-1 text-secondary"></i>{{ $supplier->phone }}</div>
                                @endif
                                @if ($supplier->email)
                                    <div class="text-secondary"><i class="bi bi-envelope me-1"></i>{{ $supplier->email }}</div>
                                @endif
                                @if (! $supplier->phone && ! $supplier->email)
                                    <span class="text-muted">—</span>
                                @endif
                            </div>
                        </td>
                        <td>
                            @if ($supplier->is_active)
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
                                <a href="{{ route('suppliers.show', $supplier) }}" class="btn btn-light btn-sm rounded-pill px-2.5 text-secondary" title="Ver Detalle">
                                    <i class="bi bi-eye"></i>
                                </a>

                                @can('update', $supplier)
                                    <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-outline-secondary btn-sm rounded-pill px-2.5" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                @endcan

                                @can('delete', $supplier)
                                    <form method="POST" action="{{ route('suppliers.destroy', $supplier) }}" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill px-2.5" onclick="return confirm('¿Estás seguro de eliminar este proveedor?')" title="Eliminar">
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
                            <i class="bi bi-truck display-4 d-block mb-2 text-secondary opacity-50"></i>
                            No se encontraron proveedores registrados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($suppliers->hasPages())
        <div class="p-3 border-top">
            {{ $suppliers->links() }}
        </div>
    @endif
</div>
@endsection
