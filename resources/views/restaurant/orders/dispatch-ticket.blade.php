<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tirilla de Despacho - {{ $order->order_number }}</title>
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
            font-size: 14px;
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

        .header-title {
            text-align: center;
            font-size: 18px;
            font-weight: 900;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .header-business {
            text-align: center;
            font-size: 15px;
            font-weight: bold;
            margin-bottom: 2px;
        }

        .header-phone {
            text-align: center;
            font-size: 13px;
            margin-bottom: 8px;
            border-bottom: 2px dashed #000;
            padding-bottom: 6px;
        }

        .order-meta {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 8px;
            border-bottom: 1px dashed #000;
            padding-bottom: 6px;
        }

        /* Marco Destacado de Entrega */
        .delivery-box {
            border: 2px solid #000;
            padding: 8px;
            margin: 8px 0;
            background-color: #fcfcfc;
        }

        .delivery-title {
            font-size: 15px;
            font-weight: 900;
            text-align: center;
            text-transform: uppercase;
            border-bottom: 1px solid #000;
            padding-bottom: 4px;
            margin-bottom: 6px;
        }

        .delivery-field {
            margin-bottom: 4px;
            font-size: 13px;
        }

        .delivery-label {
            font-weight: bold;
            display: inline-block;
        }

        .delivery-val {
            font-weight: 900;
            font-size: 14px;
        }

        .delivery-val-highlight {
            font-size: 15px;
            font-weight: 900;
            text-transform: uppercase;
            word-break: break-word;
        }

        /* Detalle de Platos */
        .items-section {
            margin-top: 8px;
            border-bottom: 1px dashed #000;
            padding-bottom: 8px;
        }

        .items-title {
            font-weight: bold;
            font-size: 13px;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        .item-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 4px 0;
            font-size: 13px;
        }

        .item-qty-name {
            font-weight: bold;
            flex-grow: 1;
        }

        .item-notes {
            margin-left: 15px;
            font-size: 12px;
            font-style: italic;
        }

        /* Totales y Liquidación Contraentrega */
        .totals-section {
            margin-top: 8px;
            padding-top: 4px;
        }

        .total-line {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            margin-bottom: 3px;
        }

        .cod-box {
            border: 2px solid #000;
            padding: 8px;
            margin-top: 8px;
            text-align: center;
            background-color: #eee;
        }

        .cod-label {
            font-size: 13px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .cod-amount {
            font-size: 22px;
            font-weight: 900;
            margin-top: 3px;
        }

        .footer-note {
            text-align: center;
            font-size: 11px;
            margin-top: 14px;
            border-top: 1px dashed #000;
            padding-top: 6px;
            font-style: italic;
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
        <button class="btn-print" onclick="window.print()">Imprimir Tirilla Despacho</button>
        <a href="{{ route('restaurant.orders.deliveries') }}" class="btn-back">Volver al Tablero</a>
    </div>

    <div class="ticket-container">
        <div class="header-business">
            {{ $order->business->name ?? 'RESTAURANTE' }}
        </div>
        <div class="header-title">
            TIRILLA DE DESPACHO
        </div>
        @if($order->business && $order->business->phone)
            <div class="header-phone">
                Tel: {{ $order->business->phone }}
            </div>
        @else
            <div class="header-phone">
                --- PEDIDO PARA REPARTO ---
            </div>
        @endif

        <div class="order-meta">
            <span>PEDIDO: {{ $order->order_number }}</span>
            <span>{{ now()->format('d/m/Y H:i') }}</span>
        </div>

        {{-- Marco Destacado de Entrega --}}
        <div class="delivery-box">
            <div class="delivery-title">
                {{ $order->order_type === 'delivery' ? 'DATOS DE ENTREGA' : 'PEDIDO PARA LLEVAR' }}
            </div>

            <div class="delivery-field">
                <span class="delivery-label">Cliente:</span>
                <span class="delivery-val">{{ $order->customer_name ?? 'Consumidor Final' }}</span>
            </div>

            @if($order->delivery_phone)
                <div class="delivery-field">
                    <span class="delivery-label">Teléfono:</span>
                    <span class="delivery-val">{{ $order->delivery_phone }}</span>
                </div>
            @endif

            @if($order->order_type === 'delivery' && $order->delivery_address)
                <div class="delivery-field">
                    <span class="delivery-label">Dirección:</span>
                    <div class="delivery-val-highlight">{{ $order->delivery_address }}</div>
                </div>
            @endif

            @if($order->delivery_notes)
                <div class="delivery-field" style="margin-top: 4px; border-top: 1px dotted #999; padding-top: 3px;">
                    <span class="delivery-label">Indicaciones:</span>
                    <div>{{ $order->delivery_notes }}</div>
                </div>
            @endif
        </div>

        {{-- Detalle de Platos --}}
        <div class="items-section">
            <div class="items-title">Detalle del Pedido:</div>
            @foreach($order->items as $item)
                <div class="item-block">
                    <div class="item-row">
                        <span class="item-qty-name">
                            [{{ (float) $item->quantity == (int) $item->quantity ? (int) $item->quantity : $item->quantity }}] {{ $item->product->name }}
                        </span>
                        <span>${{ number_format($item->subtotal, 2) }}</span>
                    </div>
                    @if($item->notes)
                        <div class="item-notes">
                            * {{ $item->notes }}
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Liquidación Contraentrega --}}
        <div class="totals-section">
            <div class="total-line">
                <span>Subtotal Comida:</span>
                <strong>${{ number_format($order->subtotal, 2) }}</strong>
            </div>

            @if($order->order_type === 'delivery')
                <div class="total-line">
                    <span>Costo de Domicilio:</span>
                    <strong>${{ number_format($order->delivery_fee, 2) }}</strong>
                </div>
            @endif

            <div class="cod-box">
                <div class="cod-label">TOTAL A COBRAR EN DESTINO:</div>
                <div class="cod-amount">${{ number_format($order->total, 2) }}</div>
            </div>
        </div>

        <div class="footer-note">
            Comprobante de despacho y entrega a domicilio.<br>
            Documento interno de control operativo.
        </div>
    </div>
</body>
</html>
