@extends('layouts.superadmin')

@section('title', 'Gestión de Negocios')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h3 class="fw-bold mb-1">Negocios Registrados</h3>
        <p class="text-secondary small mb-0">Administra los negocios de la plataforma PuntoStock</p>
    </div>
    <a href="{{ route('superadmin.businesses.create') }}" class="btn btn-primary rounded-pill px-3">
        <i class="bi bi-plus-circle me-1"></i> Crear Nuevo Negocio
    </a>
</div>

<div class="card card-dark p-3 mb-4">
    <form method="GET" action="{{ route('superadmin.businesses.index') }}" class="row g-2 align-items-center">
        <div class="col-md-5">
            <div class="input-group">
                <span class="input-group-text bg-dark border-secondary text-secondary"><i class="bi bi-search"></i></span>
                <input type="text" name="search" class="form-control bg-dark border-secondary text-light" 
                       placeholder="Buscar por nombre, NIT, teléfono o email..." value="{{ $search }}">
            </div>
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select bg-dark border-secondary text-light">
                <option value="">Todos los estados</option>
                <option value="active" {{ $selectedStatus === 'active' ? 'selected' : '' }}>Activos</option>
                <option value="inactive" {{ $selectedStatus === 'inactive' ? 'selected' : '' }}>Suspendidos</option>
            </select>
        </div>
        <div class="col-md-4 d-flex gap-2">
            <button type="submit" class="btn btn-outline-primary px-3">Filtrar</button>
            @if ($search || $selectedStatus)
                <a href="{{ route('superadmin.businesses.index') }}" class="btn btn-outline-secondary px-3">Limpiar</a>
            @endif
        </div>
    </form>
</div>

<div class="card card-dark overflow-hidden">
    <div class="table-responsive">
        <table class="table table-dark-custom align-middle mb-0">
            <thead>
                <tr>
                    <th>Negocio</th>
                    <th>NIT / Identificación</th>
                    <th>Contacto</th>
                    <th>Administrador</th>
                    <th>Vencimiento Mensualidad</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($businesses as $biz)
                    @php
                        $admin = $biz->users->first();
                    @endphp
                    <tr>
                        <td class="fw-semibold">
                            <i class="bi bi-shop text-primary me-2"></i>{{ $biz->name }}
                        </td>
                        <td>{{ $biz->nit ?: '—' }}</td>
                        <td>
                            @if ($biz->phone)
                                <div><i class="bi bi-telephone text-muted me-1"></i>{{ $biz->phone }}</div>
                            @endif
                            @if ($biz->email)
                                <div class="small text-secondary">{{ $biz->email }}</div>
                            @endif
                            @if (! $biz->phone && ! $biz->email)
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($admin)
                                <div class="fw-medium">{{ $admin->name }}</div>
                                <div class="small text-secondary">{{ $admin->email }}</div>
                            @else
                                <span class="text-muted small">Sin admin</span>
                            @endif
                        </td>
                        <td>
                            @if ($biz->subscription_ends_at)
                                <div class="fw-semibold text-light mb-1">
                                    {{ $biz->subscription_ends_at->timezone('America/Bogota')->format('d M, Y') }}
                                </div>
                                @php
                                    $daysLeft = $biz->daysUntilExpiration();
                                @endphp
                                @if ($daysLeft < 0)
                                    <span class="badge bg-danger bg-opacity-25 text-danger border border-danger border-opacity-25">
                                        <i class="bi bi-x-circle me-1"></i>Vencida ({{ abs($daysLeft) }}d)
                                    </span>
                                @elseif ($daysLeft === 0)
                                    <span class="badge bg-danger text-white">
                                        <i class="bi bi-alarm me-1"></i>Vence hoy
                                    </span>
                                @elseif ($daysLeft === 1)
                                    <span class="badge bg-warning bg-opacity-25 text-warning border border-warning border-opacity-25">
                                        <i class="bi bi-clock me-1"></i>1 día restante
                                    </span>
                                @elseif ($daysLeft <= 3)
                                    <span class="badge bg-warning bg-opacity-25 text-warning border border-warning border-opacity-25">
                                        <i class="bi bi-clock me-1"></i>{{ $daysLeft }} días restantes
                                    </span>
                                @else
                                    <span class="badge bg-info bg-opacity-25 text-info border border-info border-opacity-25">
                                        <i class="bi bi-calendar-check me-1"></i>{{ $daysLeft }} días restantes
                                    </span>
                                @endif
                            @else
                                <span class="text-muted small">Sin fecha</span>
                            @endif
                        </td>
                        <td>
                            @if ($biz->status === 'active')
                                <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-25">
                                    <i class="bi bi-check-circle me-1"></i>Activo
                                </span>
                            @else
                                <span class="badge bg-warning bg-opacity-25 text-warning border border-warning border-opacity-25">
                                    <i class="bi bi-pause-circle me-1"></i>Suspendido
                                </span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('superadmin.businesses.edit', $biz) }}" class="btn btn-outline-light" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="{{ route('superadmin.businesses.users', $biz) }}" class="btn btn-outline-info" title="Ver usuarios">
                                    <i class="bi bi-people"></i>
                                </a>
                                <form method="POST" action="{{ route('superadmin.businesses.renewSubscription', $biz) }}" class="d-inline"
                                      onsubmit="return confirm('¿Renovar 30 días calendario la suscripción de {{ $biz->name }}?');">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-success" title="Renovar 30 días">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('superadmin.businesses.resetPassword', $biz) }}" class="d-inline"
                                      onsubmit="return confirm('¿Restablecer la contraseña del administrador de {{ $biz->name }}? Se generará una clave temporal.');">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-warning" title="Restablecer contraseña del admin">
                                        <i class="bi bi-key"></i>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('superadmin.businesses.toggleStatus', $biz) }}" class="d-inline"
                                      onsubmit="return confirm('¿Seguro que deseas cambiar el estado de {{ $biz->name }}?');">
                                    @csrf
                                    @if ($biz->status === 'active')
                                        <button type="submit" class="btn btn-outline-danger" title="Suspender negocio">
                                            <i class="bi bi-pause-fill"></i>
                                        </button>
                                    @else
                                        <button type="submit" class="btn btn-outline-success" title="Reactivar negocio">
                                            <i class="bi bi-play-fill"></i>
                                        </button>
                                    @endif
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-secondary">
                            <i class="bi bi-inbox fs-3 d-block mb-2 opacity-50"></i>
                            No se encontraron negocios registrados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($businesses->hasPages())
    <div class="mt-4">
        {{ $businesses->links() }}
    </div>
@endif
@endsection
