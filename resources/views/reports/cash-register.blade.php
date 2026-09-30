@extends('layouts.app')

@section('title', 'Cuadre de Caja')

@section('content')
<style>
    @media print {
        .no-print, .navbar, .sidebar-desktop, .offcanvas, .btn, form, footer {
            display: none !important;
        }
        .main-content {
            margin: 0 !important;
            padding: 0 !important;
        }
        .card {
            border: 1px solid #ddd !important;
            box-shadow: none !important;
            break-inside: avoid;
        }
        body {
            background-color: #fff !important;
            color: #000 !important;
            font-size: 12px !important;
        }
        .print-header {
            display: block !important;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
    }
    .print-header {
        display: none;
    }
</style>

{{-- Encabezado para impresión --}}
<div class="print-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2 class="fw-bold mb-0">{{ $currentBusiness->name }}</h2>
            <p class="mb-0 text-muted">NIT: {{ $currentBusiness->nit ?? 'No registrado' }} | Cali, Colombia</p>
        </div>
        <div class="text-end">
            <h4 class="fw-bold mb-0">CUADRE DE CAJA {{ $period_type === 'monthly' ? 'MENSUAL' : 'DIARIO' }}</h4>
            <p class="mb-0 text-muted">
                Período: {{ $period_type === 'monthly' ? $selected_month : \Carbon\Carbon::parse($selected_date)->format('d/m/Y') }}
            </p>
            <small class="text-muted">Impreso: {{ now()->format('d/m/Y H:i') }}</small>
        </div>
    </div>
</div>

{{-- Barra superior de acciones --}}
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3 no-print">
    <div>
        <h3 class="fw-bold mb-1">
            <i class="bi bi-cash-coin text-primary me-2"></i>Cuadre de Caja
        </h3>
        <p class="text-muted small mb-0">
            Control discriminado de ingresos por método de pago para <strong>{{ $currentBusiness->name }}</strong>.
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button type="button" onclick="window.print()" class="btn btn-outline-secondary rounded-pill px-3 shadow-sm">
            <i class="bi bi-printer me-1"></i> Imprimir Cuadre
        </button>
        <a href="{{ route('reports.cash-register', array_merge(request()->all(), ['export' => 'csv'])) }}" class="btn btn-outline-success rounded-pill px-3 shadow-sm">
            <i class="bi bi-file-earmark-excel me-1"></i> Exportar a CSV
        </a>
        <a href="{{ route('sales.index') }}" class="btn btn-primary rounded-pill px-3 shadow-sm">
            <i class="bi bi-cart-check me-1"></i> Ir a Ventas
        </a>
    </div>
</div>

{{-- Pestañas de Reportes (Solo se muestran si el usuario es Admin para coherencia con el Hub) --}}
@if(auth()->check() && auth()->user()->isCurrentAdmin())
    <ul class="nav nav-pills mb-4 gap-2 no-print">
        <li class="nav-item">
            <a class="nav-link rounded-pill px-3 bg-white text-secondary shadow-sm" href="{{ route('reports.index') }}">
                <i class="bi bi-grid me-1"></i> Centro
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link rounded-pill px-3 active shadow-sm" href="{{ route('reports.cash-register') }}">
                <i class="bi bi-cash-coin me-1"></i> Cuadre de Caja
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link rounded-pill px-3 bg-white text-secondary shadow-sm" href="{{ route('reports.sales') }}">
                <i class="bi bi-graph-up-arrow me-1"></i> Ventas
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link rounded-pill px-3 bg-white text-secondary shadow-sm" href="{{ route('reports.inventory') }}">
                <i class="bi bi-boxes me-1"></i> Valoración Inventario
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link rounded-pill px-3 bg-white text-secondary shadow-sm" href="{{ route('reports.top-products') }}">
                <i class="bi bi-trophy me-1"></i> Top Productos
            </a>
        </li>
    </ul>
@endif

{{-- Filtros: Selector Diario / Mensual y Cajero --}}
<div class="card card-custom p-3 bg-white border-0 shadow-sm mb-4 no-print">
    <form method="GET" action="{{ route('reports.cash-register') }}" id="filterForm" class="row g-3 align-items-end">
        {{-- Tipo de Período --}}
        <div class="col-md-3 col-sm-6">
            <label class="form-label small fw-semibold text-muted mb-1">Modalidad de Cuadre</label>
            <div class="btn-group w-100" role="group">
                <input type="radio" class="btn-check" name="period_type" id="periodDaily" value="daily" {{ $period_type === 'daily' ? 'checked' : '' }} onchange="this.form.submit()">
                <label class="btn btn-outline-primary btn-sm py-2" for="periodDaily">
                    <i class="bi bi-calendar-day me-1"></i> Diario
                </label>

                <input type="radio" class="btn-check" name="period_type" id="periodMonthly" value="monthly" {{ $period_type === 'monthly' ? 'checked' : '' }} onchange="this.form.submit()">
                <label class="btn btn-outline-primary btn-sm py-2" for="periodMonthly">
                    <i class="bi bi-calendar-month me-1"></i> Mensual
                </label>
            </div>
        </div>

        {{-- Selector de Fecha o Mes --}}
        @if($period_type === 'monthly')
            <div class="col-md-3 col-sm-6">
                <label for="month" class="form-label small fw-semibold text-muted mb-1">Mes a Cuadrar</label>
                <input type="month" class="form-control form-control-sm py-2" id="month" name="month" value="{{ $selected_month }}">
            </div>
        @else
            <div class="col-md-3 col-sm-6">
                <label for="date" class="form-label small fw-semibold text-muted mb-1">Día a Cuadrar</label>
                <input type="date" class="form-control form-control-sm py-2" id="date" name="date" value="{{ $selected_date }}">
            </div>
        @endif

        {{-- Filtro por Cajero / Vendedor --}}
        <div class="col-md-3 col-sm-6">
            <label for="user_id" class="form-label small fw-semibold text-muted mb-1">Cajero / Vendedor</label>
            <select class="form-select form-select-sm py-2" id="user_id" name="user_id">
                <option value="">Todos los colaboradores</option>
                @foreach($cashiers as $c)
                    <option value="{{ $c->id }}" {{ $selected_user_id == $c->id ? 'selected' : '' }}>
                        {{ $c->name }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Botones de Filtrado --}}
        <div class="col-md-3 col-sm-6 d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm py-2 flex-grow-1 fw-semibold rounded-3">
                <i class="bi bi-funnel me-1"></i> Consultar
            </button>
            <a href="{{ route('reports.cash-register') }}" class="btn btn-light btn-sm py-2 text-muted border rounded-3" title="Restablecer a hoy">
                <i class="bi bi-arrow-clockwise"></i>
            </a>
        </div>
    </form>
</div>

{{-- Subtítulo indicador del período actual --}}
<div class="alert alert-light border shadow-sm d-flex justify-content-between align-items-center flex-wrap gap-2 py-2 px-3 mb-4 rounded-3">
    <div class="d-flex align-items-center gap-2">
        <i class="bi bi-info-circle-fill text-primary"></i>
        <span>
            Mostrando cuadre <strong>{{ $period_type === 'monthly' ? 'mensual de ' . $selected_month : 'diario del ' . \Carbon\Carbon::parse($selected_date)->translatedFormat('l, d \d\e F \d\e Y') }}</strong>
            @if($selected_user_id)
                — Cajero: <strong>{{ $cashiers->firstWhere('id', $selected_user_id)?->name }}</strong>
            @endif
        </span>
    </div>
    <span class="badge bg-secondary-subtle text-secondary font-monospace">
        {{ $sales_count }} {{ $sales_count === 1 ? 'transacción' : 'transacciones' }}
    </span>
</div>

{{-- Tarjetas KPI de Resumen General --}}
@php
    $cashAmount = $by_method['cash']['amount'] ?? 0;
    $electronicAmount = ($by_method['transfer']['amount'] ?? 0) + ($by_method['card']['amount'] ?? 0) + ($by_method['other']['amount'] ?? 0);
@endphp

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card card-custom p-3 bg-white h-100 border-0 shadow-sm border-start border-4 border-primary">
            <span class="text-muted small fw-semibold text-uppercase">Total Recaudado</span>
            <h3 class="fw-bold mb-0 mt-1 text-primary">${{ number_format($total_revenue, 0, ',', '.') }}</h3>
            <small class="text-muted">100% de los ingresos percibidos</small>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card card-custom p-3 bg-white h-100 border-0 shadow-sm border-start border-4 border-success">
            <span class="text-muted small fw-semibold text-uppercase">Efectivo Físico en Caja</span>
            <h3 class="fw-bold mb-0 mt-1 text-success">${{ number_format($cashAmount, 0, ',', '.') }}</h3>
            <small class="text-muted">
                {{ $by_method['cash']['transactions_count'] }} cobranzas en efectivo
            </small>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card card-custom p-3 bg-white h-100 border-0 shadow-sm border-start border-4 border-info">
            <span class="text-muted small fw-semibold text-uppercase">Transferencias / Nequi</span>
            <h3 class="fw-bold mb-0 mt-1 text-info">${{ number_format($by_method['transfer']['amount'] ?? 0, 0, ',', '.') }}</h3>
            <small class="text-muted">{{ $by_method['transfer']['transactions_count'] ?? 0 }} comprobantes digitales</small>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card card-custom p-3 bg-white h-100 border-0 shadow-sm border-start border-4 border-secondary">
            <span class="text-muted small fw-semibold text-uppercase">Tarjetas / Datafono</span>
            <h3 class="fw-bold mb-0 mt-1 text-dark">${{ number_format(($by_method['card']['amount'] ?? 0) + ($by_method['other']['amount'] ?? 0), 0, ',', '.') }}</h3>
            <small class="text-muted">{{ ($by_method['card']['transactions_count'] ?? 0) + ($by_method['other']['transactions_count'] ?? 0) }} transacciones bancarias</small>
        </div>
    </div>
</div>

@if($currentBusiness->isRestaurant())
    {{-- MÓDULO RESTAURANTE: CANALES DE VENTA Y RECAUDO DE FLETES --}}
    <div class="row g-3 mb-4">
        {{-- Tarjeta: Desglose por Canales de Venta --}}
        <div class="col-lg-8">
            <div class="card card-custom p-4 bg-white border-0 shadow-sm h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-diagram-3 text-primary me-2"></i>Discriminación por Canal de Venta
                    </h5>
                    <span class="badge bg-light text-dark border">Vertical Restaurante</span>
                </div>
                <div class="row g-3">
                    <div class="col-md-4 col-sm-6">
                        <div class="p-3 rounded-3 bg-light border border-primary-subtle">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <i class="bi bi-aspect-ratio text-primary fs-5"></i>
                                <span class="fw-semibold text-dark">Ventas Salón (Mesas)</span>
                            </div>
                            <h4 class="fw-bold text-primary mb-0">${{ number_format($channels['table']['total'], 0, ',', '.') }}</h4>
                            <small class="text-muted">{{ $channels['table']['count'] }} comandas liquidadas</small>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <div class="p-3 rounded-3 bg-light border border-info-subtle">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <i class="bi bi-bicycle text-info fs-5"></i>
                                <span class="fw-semibold text-dark">Ventas Domicilios</span>
                            </div>
                            <h4 class="fw-bold text-info mb-0">${{ number_format($channels['delivery']['total'], 0, ',', '.') }}</h4>
                            <small class="text-muted">{{ $channels['delivery']['count'] }} pedidos entregados</small>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <div class="p-3 rounded-3 bg-light border border-secondary-subtle">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <i class="bi bi-bag text-secondary fs-5"></i>
                                <span class="fw-semibold text-dark">Ventas Para Llevar</span>
                            </div>
                            <h4 class="fw-bold text-dark mb-0">${{ number_format($channels['takeout']['total'], 0, ',', '.') }}</h4>
                            <small class="text-muted">{{ $channels['takeout']['count'] }} órdenes en mostrador</small>
                        </div>
                    </div>
                </div>

                {{-- Recaudo de Domicilios / Fletes (Cuadre con Repartidores) --}}
                <div class="mt-3 p-3 bg-info-subtle rounded-3 border border-info text-info-emphasis d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <i class="bi bi-cash-stack fs-5 me-2 align-middle"></i>
                        <strong class="align-middle">Recaudo Total por Fletes / Domicilios:</strong>
                        <span class="d-block small text-muted mt-1">Monto acumulado por fletes para liquidación y cuadre con repartidores.</span>
                    </div>
                    <div class="text-end">
                        <span class="h4 fw-bold text-dark mb-0">${{ number_format($total_delivery_fee, 0, ',', '.') }}</span>
                        <span class="d-block small text-muted font-monospace">SUM(delivery_fee)</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tarjeta: Conciliación de Efectivo Físico vs Dinero Digital --}}
        <div class="col-lg-4">
            <div class="card card-custom p-4 bg-white border-0 shadow-sm h-100">
                <h5 class="fw-bold mb-3 text-dark">
                    <i class="bi bi-arrow-left-right text-success me-2"></i>Conciliación de Arqueo
                </h5>
                <div class="list-group list-group-flush mb-3">
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                        <div>
                            <span class="fw-semibold text-dark d-block"><i class="bi bi-cash me-1 text-success"></i>Efectivo en Gaveta</span>
                            <small class="text-muted">Total billetes restando vueltos</small>
                        </div>
                        <span class="fw-bold font-monospace text-success fs-6">${{ number_format($cash_in_drawer, 0, ',', '.') }}</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                        <div>
                            <span class="fw-semibold text-dark d-block"><i class="bi bi-phone me-1 text-info"></i>Dinero Digital (Apps / Bancos)</span>
                            <small class="text-muted">Nequi, Daviplata, Tarjetas</small>
                        </div>
                        <span class="fw-bold font-monospace text-info fs-6">${{ number_format($digital_money, 0, ',', '.') }}</span>
                    </div>
                </div>
                <div class="border-top pt-2 d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark">Total Conciliado:</span>
                    <span class="fw-bold font-monospace fs-5 text-primary">${{ number_format($total_revenue, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>
    </div>
@endif

{{-- Cuadre Discriminado por Métodos de Pago --}}
<div class="card card-custom p-4 bg-white border-0 shadow-sm mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h5 class="fw-bold mb-0">
            <i class="bi bi-wallet2 text-primary me-2"></i>Discriminación por Medios de Pago
        </h5>
        <span class="small text-muted">Conciliación contra registros contables</span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Método de Pago</th>
                    <th class="text-center" style="width: 130px;">Transacciones</th>
                    <th class="text-end" style="width: 140px;">% Cuota</th>
                    <th class="text-end" style="width: 180px;">Total Percibido</th>
                    <th>Detalle de Operación / Conciliación</th>
                </tr>
            </thead>
            <tbody>
                @foreach($by_method as $key => $method)
                    @php
                        $percentage = $total_revenue > 0 ? round(($method['amount'] / $total_revenue) * 100, 1) : 0;
                    @endphp
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-{{ $method['color'] }}-subtle text-{{ $method['color'] }} p-2 rounded-circle">
                                    <i class="bi {{ $method['icon'] }} fs-6"></i>
                                </span>
                                <div>
                                    <strong class="d-block">{{ $method['name'] }}</strong>
                                    <small class="text-muted">
                                        @if($key === 'cash')
                                            Moneda física en cajón
                                        @elseif($key === 'transfer')
                                            Cuentas de ahorro / Nequi / Daviplata
                                        @elseif($key === 'card')
                                            Vouchers datáfono débito/crédito
                                        @else
                                            Otros convenios o acuerdos
                                        @endif
                                    </small>
                                </div>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-light text-dark border font-monospace px-2 py-1">
                                {{ $method['transactions_count'] }}
                            </span>
                        </td>
                        <td class="text-end">
                            <span class="fw-semibold">{{ $percentage }}%</span>
                            <div class="progress mt-1" style="height: 4px;">
                                <div class="progress-bar bg-{{ $method['color'] }}" style="width: {{ $percentage }}%"></div>
                            </div>
                        </td>
                        <td class="text-end">
                            <span class="fw-bold font-monospace fs-6 text-{{ $method['color'] }}">
                                ${{ number_format($method['amount'], 0, ',', '.') }}
                            </span>
                        </td>
                        <td>
                            @if($key === 'cash')
                                <small class="text-muted">
                                    Billetes recibidos: <strong>${{ number_format($method['nominal_received'] ?? $method['amount'], 0, ',', '.') }}</strong>
                                    — Cambio devuelto: <span class="text-danger">-${{ number_format($method['change_given'] ?? 0, 0, ',', '.') }}</span>
                                    = <strong class="text-success">Neto en caja: ${{ number_format($method['amount'], 0, ',', '.') }}</strong>
                                </small>
                            @elseif($key === 'transfer')
                                <small class="text-muted">
                                    Verificar comprobantes en la app bancaria contra la columna Referencia
                                </small>
                            @elseif($key === 'card')
                                <small class="text-muted">
                                    Cierre de lote de datáfono debe sumar exactamente este valor
                                </small>
                            @else
                                <small class="text-muted">
                                    Conciliación según soportes internos registrados
                                </small>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="table-light">
                <tr class="fw-bold">
                    <td>TOTAL CUADRE GENERAL</td>
                    <td class="text-center font-monospace">{{ $sales_count }}</td>
                    <td class="text-end">100%</td>
                    <td class="text-end font-monospace text-primary fs-5">
                        ${{ number_format($total_revenue, 0, ',', '.') }}
                    </td>
                    <td>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                            <i class="bi bi-check-circle-fill me-1"></i> Balance cuadrado con ventas completadas
                        </span>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

{{-- Detalle Individual de Ventas del Período --}}
<div class="card card-custom p-4 bg-white border-0 shadow-sm">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h5 class="fw-bold mb-0">Detalle de Ventas Registradas</h5>
            <small class="text-muted">Listado cronológico de comprobantes que componen este cuadre</small>
        </div>
        <span class="badge bg-light text-secondary border font-monospace">
            {{ $sales->count() }} registros
        </span>
    </div>

    @if($sales->isEmpty())
        <div class="text-center py-5 text-muted">
            <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
            <h6 class="fw-bold">No se encontraron ventas para este período</h6>
            <p class="small mb-0">Verifica la fecha o mes seleccionado en los filtros superiores.</p>
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Factura</th>
                        <th>Fecha / Hora</th>
                        <th>Cajero</th>
                        <th>Cliente</th>
                        <th>Método(s) de Pago</th>
                        <th>Notas</th>
                        <th class="text-end">Total</th>
                        <th class="text-center no-print" style="width: 100px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sales as $sale)
                        <tr>
                            <td>
                                <a href="{{ route('sales.show', $sale) }}" class="fw-bold text-decoration-none font-monospace">
                                    {{ $sale->invoice_number }}
                                </a>
                                @if($currentBusiness->isRestaurant() && $sale->order_type && $sale->order_type !== 'retail')
                                    <div class="mt-1">
                                        @if($sale->order_type === 'table')
                                            <span class="badge bg-primary-subtle text-primary border" style="font-size: 0.72rem;">Salón</span>
                                        @elseif($sale->order_type === 'delivery')
                                            <span class="badge bg-info-subtle text-info border" style="font-size: 0.72rem;">Domicilio</span>
                                            @if($sale->delivery_fee > 0)
                                                <span class="badge bg-light text-dark border" style="font-size: 0.72rem;">+${{ number_format($sale->delivery_fee, 0, ',', '.') }} flete</span>
                                            @endif
                                        @elseif($sale->order_type === 'takeout')
                                            <span class="badge bg-secondary-subtle text-secondary border" style="font-size: 0.72rem;">Para Llevar</span>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="small">
                                {{ $sale->sale_date->format('d/m/Y H:i') }}
                            </td>
                            <td class="small">
                                <i class="bi bi-person me-1 text-muted"></i>{{ $sale->user ? $sale->user->name : 'N/A' }}
                            </td>
                            <td class="small">
                                {{ $sale->customer_name ?? ($sale->customer ? $sale->customer->name : config('sales.default_customer_name', 'CONSUMIDOR FINAL')) }}
                            </td>
                            <td>
                                @if($sale->payments->isNotEmpty())
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach($sale->payments as $payment)
                                            <span class="badge bg-light text-dark border font-monospace" style="font-size: 0.78rem;">
                                                {{ $payment->method_label }}: ${{ number_format($payment->amount, 0, ',', '.') }}
                                                @if($payment->reference)
                                                    <span class="text-muted">({{ $payment->reference }})</span>
                                                @endif
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="badge bg-light text-dark border font-monospace" style="font-size: 0.78rem;">
                                        {{ $sale->payment_method_label ?? $sale->payment_method }}: ${{ number_format($sale->total, 0, ',', '.') }}
                                    </span>
                                @endif
                            </td>
                            <td class="small text-muted" style="max-width: 180px;">
                                {{ $sale->notes ? Str::limit($sale->notes, 40) : '—' }}
                            </td>
                            <td class="text-end font-monospace fw-bold">
                                ${{ number_format($sale->total, 0, ',', '.') }}
                            </td>
                            <td class="text-center no-print">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('sales.print.receipt', $sale) }}" target="_blank" class="btn btn-outline-secondary" title="Imprimir Ticket">
                                        <i class="bi bi-receipt"></i>
                                    </a>
                                    <a href="{{ route('sales.show', $sale) }}" class="btn btn-outline-primary" title="Ver detalle">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
