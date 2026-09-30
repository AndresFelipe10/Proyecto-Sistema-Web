<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pre-cuenta - {{ $order->table ? $order->table->name : $order->order_number }}</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Courier New', Courier, monospace;
            background-color: #f8f9fa;
            color: #000;
            font-size: 13px;
            line-height: 1.3;
            padding: 10px;
        }

        .ticket-container {
            max-width: 80mm;
            width: 100%;
            margin: 0 auto;
            background: #fff;
            padding: 12px 8px;
            border: 1px dashed #ccc;
        }

        .business-name {
            text-align: center;
            font-size: 17px;
            font-weight: 900;
            text-transform: uppercase;
        }

        .business-nit {
            text-align: center;
            font-size: 12px;
            margin-bottom: 6px;
        }

        .ticket-title {
            text-align: center;
            font-size: 15px;
            font-weight: bold;
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 4px 0;
            margin: 6px 0;
            letter-spacing: 0.5px;
        }

        .meta-info {
            font-size: 12px;
            margin-bottom: 8px;
            border-bottom: 1px solid #000;
            padding-bottom: 6px;
        }

        .meta-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2px;
        }

        /* Tabla de consumo */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin-bottom: 8px;
        }

        .items-table th {
            border-bottom: 1px dashed #000;
            text-align: left;
            padding-bottom: 3px;
        }

        .items-table td {
            padding: 4px 0;
            vertical-align: top;
        }

        .text-end {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        /* Totales */
        .totals-section {
            border-top: 1px dashed #000;
            padding-top: 6px;
            font-size: 13px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
        }

        .grand-total {
            font-size: 18px;
            font-weight: 900;
            border-top: 1px solid #000;
            border-bottom: 2px solid #000;
            padding: 6px 0;
            margin-top: 4px;
        }

        /* Leyenda legal obligatoria */
        .legal-notice {
            margin-top: 12px;
            padding: 8px 4px;
            border: 2px solid #000;
            text-align: center;
            font-weight: 900;
            font-size: 11px;
            line-height: 1.25;
            text-transform: uppercase;
        }

        .footer-note {
            text-align: center;
            font-size: 11px;
            margin-top: 10px;
            color: #444;
        }

        .no-print {
            text-align: center;
            margin-bottom: 15px;
        }

        .btn-print {
            background-color: #0d6efd;
            color: #fff;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            margin-right: 8px;
        }

        .btn-back {
            background-color: #6c757d;
            color: #fff;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            display: inline-block;
        }

        @media print {
            body {
                background-color: #fff;
                padding: 0;
                margin: 0;
            }

            .ticket-container {
                max-width: 80mm;
                width: 80mm;
                border: none;
                padding: 4px;
                margin: 0;
            }

            .no-print {
                display: none !important;
            }

            @page {
                size: 80mm auto;
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn-print" onclick="window.print()">Imprimir Pre-cuenta</button>
        <a href="{{ route('restaurant.orders.show', $order) }}" class="btn-back">Volver a la Comanda</a>
    </div>

    <div class="ticket-container">
        <div class="business-name">
            {{ $order->business->name ?? 'RESTAURANTE' }}
        </div>
        @if($order->business && $order->business->nit)
            <div class="business-nit">
                NIT: {{ $order->business->nit }}
            </div>
        @endif

        <div class="ticket-title">
            ESTADO DE CUENTA
        </div>

        <div class="meta-info">
            <div class="meta-row">
                <span>UBICACIÓN:</span>
                <strong>{{ $order->table ? $order->table->name : 'SALÓN' }}</strong>
            </div>
            <div class="meta-row">
                <span>COMANDA:</span>
                <strong>{{ $order->order_number }}</strong>
            </div>
            <div class="meta-row">
                <span>MESERO:</span>
                <span>{{ $order->user->name ?? 'N/A' }}</span>
            </div>
            <div class="meta-row">
                <span>FECHA:</span>
                <span>{{ now()->format('d/m/Y H:i') }}</span>
            </div>
            @if($order->customer_name)
                <div class="meta-row">
                    <span>CLIENTE:</span>
                    <span>{{ $order->customer_name }}</span>
                </div>
            @endif
        </div>

        {{-- Detalle de ítems --}}
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 15%;">CANT</th>
                    <th style="width: 55%;">DESCRIPCIÓN</th>
                    <th style="width: 30%;" class="text-end">SUBTOTAL</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                    <tr>
                        <td class="text-center">
                            {{ (float) $item->quantity == (int) $item->quantity ? (int) $item->quantity : $item->quantity }}
                        </td>
                        <td>
                            {{ $item->product->name }}
                        </td>
                        <td class="text-end">
                            ${{ number_format($item->subtotal, 0, ',', '.') }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Totales --}}
        <div class="totals-section">
            <div class="total-row">
                <span>Subtotal Consumo:</span>
                <strong>${{ number_format($order->subtotal, 0, ',', '.') }}</strong>
            </div>

            @if($order->delivery_fee > 0)
                <div class="total-row">
                    <span>Servicio Entrega:</span>
                    <strong>${{ number_format($order->delivery_fee, 0, ',', '.') }}</strong>
                </div>
            @endif

            <div class="total-row grand-total">
                <span>TOTAL A PAGAR:</span>
                <span>${{ number_format($order->total, 0, ',', '.') }}</span>
            </div>
        </div>

        {{-- Leyenda legal obligatoria requerida por DIAN y normativa --}}
        <div class="legal-notice">
            ESTADO DE CONSUMO / PRE-CUENTA — DOCUMENTO INTERNO NO VÁLIDO COMO FACTURA O COMPROBANTE DE VENTA
        </div>

        <div class="footer-note">
            ¡Gracias por visitarnos!
        </div>
    </div>
</body>
</html>
