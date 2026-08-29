@extends('layouts.app')

@section('title', 'Equipo de Trabajo')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h3 class="fw-bold mb-1">Equipo de Trabajo</h3>
        <p class="text-muted small mb-0">Gestiona los colaboradores y asigna roles en <strong>{{ $currentBusiness->name }}</strong>.</p>
    </div>
    @can('create', App\Models\User::class)
        <a href="{{ route('users.create') }}" class="btn btn-primary rounded-pill px-4 fw-semibold">
            <i class="bi bi-person-plus-fill me-1"></i> Vincular Colaborador
        </a>
    @endcan
</div>

<div class="card card-custom p-0 bg-white overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr class="small text-uppercase text-muted">
                    <th class="ps-4">Usuario</th>
                    <th>Email</th>
                    <th>Rol en el Negocio</th>
                    <th>Estado</th>
                    <th>Fecha de Vinculación</th>
                    <th class="text-end pe-4">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($members as $member)
                    @php
                        $role = $roles->get($member->pivot->role_id);
                        $isActive = (bool) $member->pivot->is_active;
                        $isSelf = auth()->id() === $member->id;
                    @endphp
                    <tr>
                        <td class="ps-4">
                            <div class="d-flex align-items-center gap-2">
                                <div class="bg-light text-primary rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                    <i class="bi bi-person-fill fs-5"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold">{{ $member->name }}</div>
                                    @if ($isSelf)
                                        <span class="badge bg-info-subtle text-info border border-info-subtle small">Tú</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="text-secondary">{{ $member->email }}</span>
                        </td>
                        <td>
                            @if ($role && $role->slug === \App\Models\Role::ROLE_ADMIN)
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1.5 rounded-pill">
                                    <i class="bi bi-shield-lock-fill me-1"></i> {{ $role->name }}
                                </span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2.5 py-1.5 rounded-pill">
                                    <i class="bi bi-person-badge-fill me-1"></i> {{ $role?->name ?? 'Empleado' }}
                                </span>
                            @endif
                        </td>
                        <td>
                            @if ($isActive)
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1">
                                    <i class="bi bi-check-circle-fill me-1"></i> Activo
                                </span>
                            @else
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-1">
                                    <i class="bi bi-dash-circle-fill me-1"></i> Inactivo
                                </span>
                            @endif
                        </td>
                        <td class="text-muted small">
                            {{ $member->pivot->created_at ? \Carbon\Carbon::parse($member->pivot->created_at)->format('d/m/Y') : '—' }}
                        </td>
                        <td class="text-end pe-4">
                            <div class="d-inline-flex gap-2">
                                @can('update', $member)
                                    <a href="{{ route('users.edit', $member) }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3" title="Editar Rol">
                                        <i class="bi bi-pencil-fill me-1"></i> Editar
                                    </a>
                                @endcan

                                @if (! $isSelf)
                                    @can('deactivate', $member)
                                        <form method="POST" action="{{ route('users.toggleActive', $member) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm {{ $isActive ? 'btn-outline-danger' : 'btn-outline-success' }} rounded-pill px-3" onclick="return confirm('¿Estás seguro de cambiar el estado de este colaborador?')">
                                                <i class="bi {{ $isActive ? 'bi-person-x-fill' : 'bi-person-check-fill' }} me-1"></i>
                                                {{ $isActive ? 'Desactivar' : 'Reactivar' }}
                                            </button>
                                        </form>
                                    @endcan
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
