@extends('layouts.app')

@section('title', 'Centro de Reportes')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h3 class="fw-bold mb-1">Centro de Reportes</h3>
        <p class="text-muted small mb-0">Informes analíticos, financieros y operativos para <strong>{{ $currentBusiness->name }}</strong>.</p>
    </div>
    <span class="badge bg-primary-subtle text-primary px-3 py-2 rounded-pill fs-6">
        <i class="bi bi-shield-lock me-1"></i> Rol Administrador
    </span>
</div>

{{-- Accesos a los Reportes Principales --}}
<div class="row g-4 mb-4">
    <div class="col-lg-3 col-md-6">
        <div class="card card-custom p-4 bg-white h-100 border-0 shadow-sm d-flex flex-column border-start border-4 border-primary">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="bg-primary-subtle text-primary p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 54px; height: 54px;">
                    <i class="bi bi-cash-coin fs-3"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-0">Cuadre de Caja</h5>
                    <small class="text-muted">Diario y Mensual</small>
                </div>
            </div>
            <p class="text-muted small mb-4 flex-grow-1">
                Concilia los ingresos reales del día o del mes, discriminados estrictamente por efectivo, transferencias, Nequi y tarjetas con exportación e impresión.
            </p>
            <div class="d-flex justify-content-between align-items-center pt-3 border-top mt-auto">
                <span class="text-primary fw-semibold small">Disponible para el equipo</span>
                <a href="{{ route('reports.cash-register') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-semibold">
                    Ver Cuadre <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6">
        <div class="card card-custom p-4 bg-white h-100 border-0 shadow-sm d-flex flex-column">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="bg-success-subtle text-success p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 54px; height: 54px;">
                    <i class="bi bi-graph-up-arrow fs-3"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-0">Reporte de Ventas</h5>
                    <small class="text-muted">Ingresos y transacciones</small>
                </div>
            </div>
            <p class="text-muted small mb-4 flex-grow-1">
                Analiza las ventas en rangos de fecha personalizados, ticket promedio, descuentos aplicados y distribución por métodos con exportación a CSV.
            </p>
            <div class="d-flex justify-content-between align-items-center pt-3 border-top mt-auto">
                <span class="fw-bold text-success">${{ number_format($salesSummary['total_revenue'] ?? 0, 0, ',', '.') }}</span>
                <a href="{{ route('reports.sales') }}" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-semibold">
                    Ver Reporte <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6">
        <div class="card card-custom p-4 bg-white h-100 border-0 shadow-sm d-flex flex-column">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="bg-info-subtle text-info p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 54px; height: 54px;">
                    <i class="bi bi-boxes fs-3"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-0">Valoración Inventario</h5>
                    <small class="text-muted">Capital en existencias</small>
                </div>
            </div>
            <p class="text-muted small mb-4 flex-grow-1">
                Conoce el valor monetario de tu inventario al costo y precio de venta, margen bruto proyectado y unidades disponibles por producto y categoría.
            </p>
            <div class="d-flex justify-content-between align-items-center pt-3 border-top mt-auto">
                <span class="fw-bold text-info">${{ number_format($inventorySummary['total_cost_valuation'] ?? 0, 0, ',', '.') }}</span>
                <a href="{{ route('reports.inventory') }}" class="btn btn-sm btn-outline-info rounded-pill px-3 fw-semibold">
                    Ver Reporte <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6">
        <div class="card card-custom p-4 bg-white h-100 border-0 shadow-sm d-flex flex-column">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="bg-warning-subtle text-warning p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 54px; height: 54px;">
                    <i class="bi bi-trophy fs-3"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-0">Top y Rentabilidad</h5>
                    <small class="text-muted">Productos más rentables</small>
                </div>
            </div>
            <p class="text-muted small mb-4 flex-grow-1">
                Descubre tus productos estrella, unidades vendidas, costo de mercancía vendida (COGS) y margen de ganancia real por artículo.
            </p>
            <div class="d-flex justify-content-between align-items-center pt-3 border-top mt-auto">
                <span class="text-muted small">Ranking dinámico</span>
                <a href="{{ route('reports.top-products') }}" class="btn btn-sm btn-outline-warning rounded-pill px-3 fw-semibold text-dark">
                    Ver Reporte <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
