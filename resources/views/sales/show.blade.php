@extends('layouts.app')

@section('title', 'Venta ' . $sale->invoice_number)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div class="d-flex align-items-center gap-3">
        <a href="{{ route('sales.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            <i class="bi bi-arrow-left me-1"></i> Volver
        </a>
        <div>
            <h3 class="fw-bold mb-0">Comprobante de Venta</h3>
            <span class="font-monospace text-primary fw-bold">{{ $sale->invoice_number }}</span>
            @if($sale->order_type && $sale->order_type !== 'retail')
                <span class="badge bg-light text-dark border ms-2">
                    <i class="bi bi-egg-fried me-1 text-warning"></i>
                    @if($sale->order_type === 'table') Salón / Mesa @elseif($sale->order_type === 'delivery') Domicilio @else Para Llevar @endif
                </span>
            @endif
        </div>
    </div>

    <div class="d-flex align-items-center gap-2">
        @if ($sale->status === 'completed')
            <a href="{{ route('sales.print.invoice', $sale) }}" target="_blank"
               class="btn btn-outline-primary btn-sm rounded-pill px-3" title="Imprimir comprobante tamaño carta/A4">
                <i class="bi bi-printer me-1"></i> Imprimir comprobante (carta)
            </a>
            <a href="{{ route('sales.print.receipt', $sale) }}" target="_blank"
               class="btn btn-outline-secondary btn-sm rounded-pill px-3" title="Imprimir comprobante para impresora térmica 80mm">
                <i class="bi bi-receipt me-1"></i> Imprimir comprobante (ticket)
            </a>
            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-2">
                <i class="bi bi-check-circle-fill me-1"></i> Completada
            </span>
            @can('delete', $sale)
                <form method="POST" action="{{ route('sales.destroy', $sale) }}"
                      onsubmit="return confirm('¿Está seguro de anular esta venta? El stock de los productos será restaurado.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill px-3">
                        <i class="bi bi-x-circle me-1"></i> Anular Venta
                    </button>
                </form>
            @endcan
        @else
            <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3 py-2">
                <i class="bi bi-x-circle-fill me-1"></i> Anulada
            </span>
        @endif
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-9">
        {{-- Cabecera --}}
        <div class="card card-custom p-4 mb-4">
            <div class="row g-3">
                <div class="col-sm-6 col-md-3">
                    <span class="text-muted small d-block">Fecha</span>
                    <span class="fw-semibold">{{ $sale->sale_date->format('d/m/Y H:i') }}</span>
                </div>
                <div class="col-sm-6 col-md-3">
                    <span class="text-muted small d-block">Cliente</span>
                    <span class="fw-semibold">
                        @if ($sale->customer)
                            <a href="{{ route('customers.show', $sale->customer) }}" class="text-decoration-none">
                                {{ $sale->customer_name ?? $sale->customer->name }}
                            </a>
                        @else
                            <span>{{ $sale->customer_name ?? config('sales.default_customer_name') }}</span>
                        @endif
                    </span>
                    @if ($sale->customer_document)
                        <span class="text-muted small d-block font-monospace">Doc: {{ $sale->customer_document }}</span>
                    @endif
                </div>
                <div class="col-sm-6 col-md-3">
                    <span class="text-muted small d-block">Vendedor</span>
                    <span class="fw-semibold">{{ $sale->user->name }}</span>
                </div>
                <div class="col-sm-6 col-md-3">
                    <span class="text-muted small d-block">Método de Pago</span>
                    <span class="fw-semibold">
                        @switch($sale->payment_method)
                            @case('cash') <i class="bi bi-cash text-success me-1"></i> Efectivo @break
                            @case('transfer') <i class="bi bi-bank text-info me-1"></i> Transferencia @break
                            @case('card') <i class="bi bi-credit-card text-primary me-1"></i> Tarjeta @break
                            @case('mixed') <i class="bi bi-wallet2 text-warning me-1"></i> Pago Mixto @break
                            @default <i class="bi bi-three-dots text-secondary me-1"></i> Otro
                        @endswitch
                    </span>
                </div>
            </div>

            @if ($sale->notes)
                <div class="mt-3 pt-3 border-top">
                    <span class="text-muted small d-block">Notas</span>
                    <span>{{ $sale->notes }}</span>
                </div>
            @endif
        </div>

        {{-- Detalle de productos --}}
        <div class="card card-custom mb-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Producto</th>
                            <th class="text-center">Cantidad</th>
                            <th class="text-end">P. Unitario</th>
                            <th class="text-end pe-4">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sale->details as $detail)
                            <tr>
                                <td class="ps-4">
                                    <span class="fw-semibold">{{ $detail->product->name }}</span>
                                    @if ($detail->product->sku)
                                        <small class="text-muted font-monospace ms-2">{{ $detail->product->sku }}</small>
                                    @endif
                                </td>
                                <td class="text-center">{{ $detail->quantity }}</td>
                                <td class="text-end">${{ number_format($detail->unit_price, 0, ',', '.') }}</td>
                                <td class="text-end pe-4 fw-semibold">${{ number_format($detail->subtotal, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Métodos de Pago Aplicados --}}
        @if ($sale->payments->isNotEmpty())
            <div class="card card-custom p-4 mb-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-wallet2 text-primary me-2"></i>Distribución de Pagos</h6>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-3">Método</th>
                                <th>Referencia</th>
                                <th class="text-end">Monto Aplicado</th>
                                <th class="text-end">Efectivo Recibido</th>
                                <th class="text-end pe-3">Vuelto / Cambio</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sale->payments as $payment)
                                <tr>
                                    <td class="ps-3">
                                        <span class="fw-semibold">
                                            @switch($payment->method instanceof \App\Enums\PaymentMethod ? $payment->method->value : $payment->method)
                                                @case('cash') <i class="bi bi-cash text-success me-1"></i> Efectivo @break
                                                @case('transfer') <i class="bi bi-bank text-info me-1"></i> Transferencia @break
                                                @case('card') <i class="bi bi-credit-card text-primary me-1"></i> Tarjeta @break
                                                @default <i class="bi bi-three-dots text-secondary me-1"></i> Otro
                                            @endswitch
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-muted small">{{ $payment->reference ?: '—' }}</span>
                                    </td>
                                    <td class="text-end fw-semibold">${{ number_format($payment->amount, 0, ',', '.') }}</td>
                                    <td class="text-end text-muted">
                                        {{ $payment->cash_received !== null ? '$' . number_format($payment->cash_received, 0, ',', '.') : '—' }}
                                    </td>
                                    <td class="text-end pe-3 text-muted">
                                        {{ $payment->change_given !== null ? '$' . number_format($payment->change_given, 0, ',', '.') : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Totales --}}
        <div class="card card-custom p-4">
            <div class="row justify-content-end">
                <div class="col-md-5">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Subtotal</span>
                        <span class="fw-semibold">${{ number_format($sale->subtotal, 0, ',', '.') }}</span>
                    </div>
                    @if ($sale->discount > 0)
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Descuento</span>
                            <span class="fw-semibold text-danger">
                                {{ number_format($sale->discount_percentage, 1) }}% (-${{ number_format($sale->discount, 0, ',', '.') }})
                            </span>
                        </div>
                    @endif
                    @if ($sale->delivery_fee > 0)
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Domicilio / Flete</span>
                            <span class="fw-semibold text-dark">
                                ${{ number_format($sale->delivery_fee, 0, ',', '.') }}
                            </span>
                        </div>
                    @endif
                    <hr>
                    <div class="d-flex justify-content-between">
                        <span class="fw-bold fs-5">Total</span>
                        <span class="fw-bold fs-5 text-primary">${{ number_format($sale->total, 0, ',', '.') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
