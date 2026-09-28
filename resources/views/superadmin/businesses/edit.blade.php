@extends('layouts.superadmin')

@section('title', 'Editar Negocio')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card card-dark p-4 p-md-5">
            <div class="d-flex justify-content-between align-items-center mb-4 border-bottom border-secondary pb-3">
                <div>
                    <h3 class="fw-bold mb-1">Editar Negocio</h3>
                    <p class="text-secondary small mb-0">Modifica los datos comerciales de {{ $business->name }}</p>
                </div>
                <a href="{{ route('superadmin.businesses.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                    <i class="bi bi-arrow-left me-1"></i> Volver
                </a>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger mb-4 py-2 px-3 small rounded-3 bg-danger bg-opacity-25 text-danger-emphasis border-0">
                    <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> Corrige los siguientes errores:</div>
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Estado de Suscripción y Renovación --}}
            <div class="card card-dark p-3 mb-4 border border-secondary border-opacity-50 bg-secondary bg-opacity-10">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <div class="text-secondary small fw-medium">Vencimiento Mensualidad</div>
                        <div class="d-flex align-items-center gap-2 mt-1">
                            <span class="fs-5 fw-bold text-white">
                                {{ $business->subscription_ends_at ? $business->subscription_ends_at->timezone('America/Bogota')->format('d M, Y') : 'Sin fecha asignada' }}
                            </span>
                            @if ($business->subscription_ends_at)
                                @php
                                    $daysLeft = $business->daysUntilExpiration();
                                @endphp
                                @if ($daysLeft < 0)
                                    <span class="badge bg-danger bg-opacity-75 text-white border border-danger">
                                        <i class="bi bi-slash-circle-fill me-1"></i>⛔ Vencida (hace {{ abs($daysLeft) }} {{ abs($daysLeft) === 1 ? 'día' : 'días' }})
                                    </span>
                                @elseif ($business->isCriticalExpiring())
                                    <span class="badge bg-danger text-white">
                                        <i class="bi bi-exclamation-octagon-fill me-1"></i>🚨 Notif. Crítica ({{ $daysLeft === 0 ? 'Vence hoy' : '1d restante' }})
                                    </span>
                                @elseif ($business->isExpiringSoon())
                                    <span class="badge bg-warning text-dark border border-warning">
                                        <i class="bi bi-bell-fill me-1"></i>🔔 Notif. Preventiva ({{ $daysLeft }}d restantes)
                                    </span>
                                @else
                                    <span class="badge bg-info bg-opacity-25 text-info-emphasis border border-info border-opacity-25">
                                        <i class="bi bi-check-circle-fill me-1"></i>✅ Al día (Sin notif.)
                                    </span>
                                @endif
                            @endif
                        </div>
                    </div>
                    <form method="POST" action="{{ route('superadmin.businesses.renewSubscription', $business) }}"
                          onsubmit="return confirm('¿Renovar 30 días calendario la suscripción de {{ $business->name }}?');">
                        @csrf
                        <button type="submit" class="btn btn-success btn-sm px-3 fw-semibold">
                            <i class="bi bi-arrow-repeat me-1"></i> Renovar 30 días
                        </button>
                    </form>
                </div>
            </div>

            <form method="POST" action="{{ route('superadmin.businesses.update', $business) }}" novalidate>
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label for="name" class="form-label small fw-semibold text-secondary">Nombre Comercial <span class="text-danger">*</span></label>
                    <input type="text" class="form-control bg-dark border-secondary text-light @error('name') is-invalid @enderror"
                           id="name" name="name" value="{{ old('name', $business->name) }}" required autofocus>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="nit" class="form-label small fw-semibold text-secondary">NIT / Identificación</label>
                        <input type="text" class="form-control bg-dark border-secondary text-light @error('nit') is-invalid @enderror"
                               id="nit" name="nit" value="{{ old('nit', $business->nit) }}">
                    </div>
                    <div class="col-md-6">
                        <label for="phone" class="form-label small fw-semibold text-secondary">Teléfono / WhatsApp</label>
                        <input type="text" class="form-control bg-dark border-secondary text-light @error('phone') is-invalid @enderror"
                               id="phone" name="phone" value="{{ old('phone', $business->phone) }}">
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="email" class="form-label small fw-semibold text-secondary">Correo Electrónico</label>
                        <input type="email" class="form-control bg-dark border-secondary text-light @error('email') is-invalid @enderror"
                               id="email" name="email" value="{{ old('email', $business->email) }}">
                    </div>
                    <div class="col-md-6">
                        <label for="address" class="form-label small fw-semibold text-secondary">Dirección</label>
                        <input type="text" class="form-control bg-dark border-secondary text-light @error('address') is-invalid @enderror"
                               id="address" name="address" value="{{ old('address', $business->address) }}">
                    </div>
                </div>

                <div class="mb-4 p-3 rounded-3 border border-secondary border-opacity-50 bg-dark">
                    <label for="subscription_ends_at" class="form-label small fw-semibold text-light">
                        <i class="bi bi-calendar-event text-primary me-1"></i> Fecha de Vencimiento de Suscripción (Corte Mensual)
                    </label>
                    <input type="date" class="form-control bg-dark border-secondary text-light @error('subscription_ends_at') is-invalid @enderror"
                           id="subscription_ends_at" name="subscription_ends_at"
                           value="{{ old('subscription_ends_at', $business->subscription_ends_at?->timezone('America/Bogota')->format('Y-m-d')) }}">
                    @error('subscription_ends_at')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text text-secondary small mt-1">
                        Puedes ajustar libremente la fecha límite de mensualidad para corregir días o configurar un ciclo específico.
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 border-top border-secondary pt-3">
                    <a href="{{ route('superadmin.businesses.index') }}" class="btn btn-outline-secondary px-4">Cancelar</a>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold">
                        <i class="bi bi-save me-1"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
