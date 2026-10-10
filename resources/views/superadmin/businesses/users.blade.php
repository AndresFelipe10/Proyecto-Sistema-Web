@extends('layouts.superadmin')

@section('title', 'Usuarios de ' . $business->name)

@section('content')
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-4 gap-3">
    <div>
        <h1 class="fs-3 fw-bold text-white mb-1">Usuarios de {{ $business->name }}</h1>
        <p class="text-secondary small mb-0">Listado de colaboradores vinculados a este negocio</p>
    </div>
    <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center gap-2 w-100 w-sm-auto">
        <a href="{{ route('superadmin.businesses.users.create', $business) }}" class="btn btn-primary fw-semibold d-inline-flex align-items-center justify-content-center" style="min-height: 42px;">
            <i class="bi bi-person-plus me-1"></i> + Agregar Colaborador
        </a>
        <a href="{{ route('superadmin.businesses.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 d-inline-flex align-items-center justify-content-center" style="min-height: 42px;">
            <i class="bi bi-arrow-left me-1"></i> Volver a Negocios
        </a>
    </div>
</div>

@if (session('temp_password'))
    <div class="alert alert-success border-0 shadow-sm d-flex align-items-center mb-4 text-white" style="background-color: #198754;">
        <i class="bi bi-key-fill fs-2 me-3"></i>
        <div class="flex-grow-1">
            <h5 class="fw-bold mb-1">¡Colaborador Registrado con Éxito!</h5>
            <p class="mb-0">
                Credenciales para entregar al comercio: 
                <strong>{{ session('created_user_email') }}</strong> &bull; 
                Contraseña temporal: <code class="fs-6 fw-bold bg-dark text-warning px-2 py-1 rounded">{{ session('temp_password') }}</code>
                <span class="badge bg-warning text-dark ms-2">Debe cambiarla al primer inicio de sesión</span>
            </p>
        </div>
    </div>
@endif

<div class="card card-dark border-secondary border-opacity-25 rounded-4 overflow-hidden shadow-sm">
    <div class="table-responsive">
        <table class="table table-dark-custom align-middle mb-0">
            <thead>
                <tr>
                    <th style="min-width: 160px;">Nombre</th>
                    <th style="min-width: 180px;">Correo Electrónico</th>
                    <th style="min-width: 130px;">Rol</th>
                    <th style="min-width: 110px;">Estado</th>
                    <th style="min-width: 150px;">Fecha de Vinculación</th>
                    <th class="text-end" style="min-width: 140px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    @php
                        $role = $roles[$user->pivot->role_id] ?? null;
                        $isActive = (bool) $user->pivot->is_active;
                    @endphp
                    <tr>
                        <td class="fw-medium">
                            <i class="bi bi-person-circle text-primary me-2"></i>{{ $user->name }}
                        </td>
                        <td>{{ $user->email }}</td>
                        <td>
                            @if ($role && $role->slug === 'admin')
                                <span class="badge bg-primary bg-opacity-25 text-primary border border-primary border-opacity-25">
                                    <i class="bi bi-shield-check me-1"></i>Administrador
                                </span>
                            @else
                                <span class="badge bg-secondary bg-opacity-25 text-secondary border border-secondary border-opacity-25">
                                    <i class="bi bi-person me-1"></i>{{ $role->name ?? 'Colaborador' }}
                                </span>
                            @endif
                        </td>
                        <td>
                            @if ($isActive)
                                <span class="badge bg-success bg-opacity-25 text-success">
                                    <i class="bi bi-check-circle me-1"></i>Activo
                                </span>
                            @else
                                <span class="badge bg-danger bg-opacity-25 text-danger">
                                    <i class="bi bi-x-circle me-1"></i>Inactivo
                                </span>
                            @endif
                        </td>
                        <td class="small text-secondary">
                            {{ $user->pivot->created_at ? \Carbon\Carbon::parse($user->pivot->created_at)->format('d/m/Y H:i') : '—' }}
                        </td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('superadmin.businesses.toggleUserStatus', [$business, $user]) }}" class="d-inline"
                                  onsubmit="return confirm('¿Seguro que deseas {{ $isActive ? 'desactivar' : 'activar' }} a {{ $user->name }} en este negocio?');">
                                @csrf
                                @if ($isActive)
                                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Desactivar usuario">
                                        <i class="bi bi-person-x me-1"></i>Desactivar
                                    </button>
                                @else
                                    <button type="submit" class="btn btn-outline-success btn-sm" title="Activar usuario">
                                        <i class="bi bi-person-check me-1"></i>Activar
                                    </button>
                                @endif
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-secondary">
                            No hay usuarios vinculados a este negocio.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
