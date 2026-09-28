<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante de venta {{ $sale->invoice_number }} | {{ config('app.name') }}</title>
    @include('partials.brand-head')
    <style>
        /* ── Thermal receipt: 80mm width ── */
        @page {
            size: 80mm auto;
            margin: 0;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Courier New', 'Lucida Console', monospace;
            font-size: 12px;
            color: #000;
            line-height: 1.4;
            background: #fff;
            width: 80mm;
            max-width: 302px;
            margin: 0 auto;
            padding: 5mm 3mm;
        }

        /* ── Header ── */
        .receipt-header {
            text-align: center;
            border-bottom: 1px dashed #000;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }
        .receipt-header .business-name {
            font-size: 16px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .receipt-header .business-detail {
            font-size: 10px;
            color: #333;
        }

        /* ── Invoice Info ── */
        .receipt-info {
            border-bottom: 1px dashed #000;
            padding-bottom: 8px;
            margin-bottom: 8px;
            font-size: 11px;
        }
        .receipt-info .row {
            display: flex;
            justify-content: space-between;
        }

        /* ── Items ── */
        .items-header {
            display: flex;
            justify-content: space-between;
            font-weight: 700;
            font-size: 11px;
            border-bottom: 1px solid #000;
            padding-bottom: 3px;
            margin-bottom: 5px;
        }
        .item-line {
            margin-bottom: 4px;
            font-size: 11px;
        }
        .item-name {
            font-weight: 600;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .item-detail {
            display: flex;
            justify-content: space-between;
            color: #333;
            padding-left: 8px;
        }

        /* ── Totals ── */
        .totals {
            border-top: 1px dashed #000;
            margin-top: 8px;
            padding-top: 8px;
        }
        .totals .row {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            margin-bottom: 2px;
        }
        .totals .discount { color: #333; }
        .totals .total-row {
            font-size: 16px;
            font-weight: 700;
            border-top: 1px solid #000;
            padding-top: 5px;
            margin-top: 5px;
        }

        /* ── Footer ── */
        .receipt-footer {
            border-top: 1px dashed #000;
            margin-top: 10px;
            padding-top: 8px;
            text-align: center;
            font-size: 10px;
            color: #555;
        }
        .receipt-footer .thanks {
            font-size: 13px;
            font-weight: 700;
            color: #000;
            margin-bottom: 4px;
        }

        /* ── No-print elements ── */
        .no-print {
            text-align: center;
            margin-bottom: 10px;
            width: 80mm;
            max-width: 302px;
        }
        .no-print button {
            padding: 8px 20px;
            font-size: 12px;
            background: #333;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin: 2px;
        }
        .no-print button:hover { background: #555; }

        @media print {
            .no-print { display: none !important; }
            body { padding: 2mm; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">🧾 Imprimir comprobante (ticket)</button>
        <button onclick="window.close()">Cerrar</button>
    </div>

    {{-- Header --}}
    <div class="receipt-header">
        <div class="business-name">{{ $business->name }}</div>
        @if($business->nit)
            <div class="business-detail">NIT: {{ $business->nit }}</div>
        @endif
        @if($business->address)
            <div class="business-detail">{{ $business->address }}</div>
        @endif
        @if($business->phone)
            <div class="business-detail">Tel: {{ $business->phone }}</div>
        @endif
    </div>

    {{-- Invoice Info --}}
    <div class="receipt-info">
        <div class="row">
            <span>Comprobante:</span>
            <span><strong>{{ $sale->invoice_number }}</strong></span>
        </div>
        <div class="row">
            <span>Fecha:</span>
            <span>{{ $sale->sale_date->format('d/m/Y H:i') }}</span>
        </div>
        <div class="row">
            <span>Cliente:</span>
            <span>{{ $sale->customer_name ?? ($sale->customer ? $sale->customer->name : config('sales.default_customer_name')) }}</span>
        </div>
        <div class="row">
            <span>Doc/NIT:</span>
            <span>{{ $sale->customer_document ?? ($sale->customer ? ($sale->customer->document ?? $sale->customer->identification_number) : config('sales.default_customer_document')) }}</span>
        </div>
        <div class="row">
            <span>Vendedor:</span>
            <span>{{ $sale->user->name }}</span>
        </div>
        <div class="row">
            <span>Pago:</span>
            <span>
                @switch($sale->payment_method)
                    @case('cash') Efectivo @break
                    @case('transfer') Transferencia @break
                    @case('card') Tarjeta @break
                    @case('mixed') Mixto @break
                    @default Otro
                @endswitch
            </span>
        </div>
    </div>

    {{-- Items --}}
    <div class="items-header">
        <span>PRODUCTO</span>
        <span>TOTAL</span>
    </div>

    @foreach($sale->details as $detail)
        <div class="item-line">
            <span class="item-name">{{ $detail->product->name }}</span>
            <div class="item-detail">
                <span>{{ $detail->quantity }} x ${{ number_format($detail->unit_price, 0, ',', '.') }}</span>
                <span>${{ number_format($detail->subtotal, 0, ',', '.') }}</span>
            </div>
        </div>
    @endforeach

    {{-- Totals --}}
    <div class="totals">
        <div class="row">
            <span>Subtotal:</span>
            <span>${{ number_format($sale->subtotal, 0, ',', '.') }}</span>
        </div>
        @if($sale->discount > 0)
            <div class="row discount">
                <span>Desc. ({{ number_format($sale->discount_percentage, 1) }}%):</span>
                <span>-${{ number_format($sale->discount, 0, ',', '.') }}</span>
            </div>
        @endif
        <div class="row total-row">
            <span>TOTAL:</span>
            <span>${{ number_format($sale->total, 0, ',', '.') }}</span>
        </div>
    </div>

    {{-- Formas de pago aplicadas --}}
    @if($sale->payments->isNotEmpty())
        <div class="totals" style="border-top: 1px dashed #333; margin-top: 6px; padding-top: 4px;">
            <div style="font-weight: bold; font-size: 11px; margin-bottom: 3px; text-transform: uppercase;">Formas de pago:</div>
            @foreach($sale->payments as $payment)
                <div class="row">
                    <span>{{ $payment->method_label }}{{ $payment->reference ? ' (' . $payment->reference . ')' : '' }}:</span>
                    <span>${{ number_format($payment->amount, 0, ',', '.') }}</span>
                </div>
                @if(($payment->method instanceof \App\Enums\PaymentMethod ? $payment->method->value : $payment->method) === 'cash')
                    @if($payment->cash_received !== null)
                        <div class="row" style="color: #444; font-size: 10px;">
                            <span>  Efectivo recibido:</span>
                            <span>${{ number_format($payment->cash_received, 0, ',', '.') }}</span>
                        </div>
                        <div class="row" style="color: #444; font-size: 10px;">
                            <span>  Vueltos:</span>
                            <span>${{ number_format($payment->change_given, 0, ',', '.') }}</span>
                        </div>
                    @endif
                @endif
            @endforeach
        </div>
    @endif

    {{-- Footer --}}
    <div class="receipt-footer">
        <div class="thanks">¡Gracias por su compra!</div>
        <div>{{ $business->name }}</div>
        <div>{{ now()->format('d/m/Y H:i') }}</div>
        <div>Cali, Colombia</div>
        <div style="margin-top: 8px; font-size: 9px; line-height: 1.3; color: #444; font-style: italic;">{{ config('sales.legal_disclaimer') }}</div>
    </div>

    <script>
        // Auto-print when opened in a new tab
        window.addEventListener('load', function() {
            setTimeout(function() { window.print(); }, 500);
        });
    </script>
</body>
</html>
