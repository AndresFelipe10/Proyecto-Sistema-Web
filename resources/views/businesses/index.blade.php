@extends('layouts.app')

@section('title', 'Mis Emprendimientos')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h3 class="fw-bold mb-1">Mis Emprendimientos</h3>
        <p class="text-muted small mb-0">Gestiona los negocios asociados a tu cuenta y cambia entre ellos.</p>
    </div>
    <a href="{{ route('businesses.create') }}" class="btn btn-primary rounded-pill px-4 fw-semibold">
        <i class="bi bi-plus-circle-fill me-1"></i> Nuevo Emprendimiento
    </a>
</div>

<div class="row g-4">
    @foreach ($businesses as $business)
        @php
            $isActive = $currentBusiness && $currentBusiness->id === $business->id;
            $role = \App\Models\Role::find($business->pivot->role_id);
        @endphp
        <div class="col-md-6 col-lg-4">
            <div class="card card-custom p-4 bg-white h-100 {{ $isActive ? 'border-primary border-2 shadow-sm' : '' }}">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="p-2 bg-light rounded-3 text-primary">
                            <i class="bi bi-shop fs-4"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0">{{ $business->name }}</h5>
                            <span class="badge bg-secondary-subtle text-secondary small">
                                Rol: {{ $role?->name ?? 'Miembro' }}
                            </span>
                        </div>
                    </div>
                    @if ($isActive)
                        <span class="badge bg-success rounded-pill px-2.5 py-1.5">
                            <i class="bi bi-check-circle-fill me-1"></i> Activo
                        </span>
                    @endif
                </div>

                <ul class="list-unstyled small text-muted mb-4">
                    @if ($business->nit)
                        <li class="mb-1"><i class="bi bi-card-text me-2"></i><strong>NIT:</strong> {{ $business->nit }}</li>
                    @endif
                    @if ($business->phone)
                        <li class="mb-1"><i class="bi bi-telephone me-2"></i><strong>Tel:</strong> {{ $business->phone }}</li>
                    @endif
                    @if ($business->email)
                        <li class="mb-1"><i class="bi bi-envelope me-2"></i><strong>Email:</strong> {{ $business->email }}</li>
                    @endif
                    @if ($business->address)
                        <li class="mb-1"><i class="bi bi-geo-alt me-2"></i><strong>Ubicación:</strong> {{ $business->address }}</li>
                    @endif
                </ul>

                <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center gap-2">
                    @if (! $isActive)
                        <form method="POST" action="{{ route('businesses.switch', $business) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                                <i class="bi bi-arrow-repeat me-1"></i> Conmutar
                            </button>
                        </form>
                    @else
                        <span class="text-success small fw-semibold"><i class="bi bi-shield-check me-1"></i> Negocio en uso</span>
                    @endif

                    @can('update', $business)
                        <a href="{{ route('businesses.edit', $business) }}" class="btn btn-light btn-sm rounded-pill px-3 text-secondary">
                            <i class="bi bi-pencil-square me-1"></i> Editar
                        </a>
                    @endcan
                </div>
            </div>
        </div>
    @endforeach
</div>
@endsection
