<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comanda de Cocina - {{ $order->order_number }}</title>
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
            font-size: 20px;
            font-weight: 900;
            letter-spacing: 1px;
            border-bottom: 2px dashed #000;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }

        .reprint-badge {
            text-align: center;
            font-weight: bold;
            font-size: 13px;
            margin-bottom: 6px;
        }

        .info-section {
            border-bottom: 1px dashed #000;
            padding-bottom: 8px;
            margin-bottom: 10px;
            font-size: 13px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
        }

        .mesa-destacada {
            font-size: 22px;
            font-weight: 900;
            text-align: center;
            padding: 4px 0;
            border: 2px solid #000;
            margin: 6px 0;
            text-transform: uppercase;
        }

        .items-list {
            margin-top: 8px;
        }

        .item-block {
            padding: 6px 0;
            border-bottom: 1px dotted #555;
        }

        .item-main {
            font-size: 16px;
            font-weight: 900;
            display: flex;
            align-items: flex-start;
        }

        .item-qty {
            min-width: 38px;
            display: inline-block;
        }

        .item-name {
            flex-grow: 1;
            text-transform: uppercase;
        }

        .item-notes {
            margin-top: 4px;
            margin-left: 20px;
            padding: 3px 6px;
            background: #eee;
            border-left: 3px solid #000;
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .footer-note {
            text-align: center;
            font-size: 11px;
            margin-top: 14px;
            border-top: 1px dashed #000;
            padding-top: 6px;
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
        <button class="btn-print" onclick="window.print()">Imprimir Ticket Cocina</button>
        <a href="{{ route('restaurant.orders.show', $order) }}" class="btn-back">Volver a la Comanda</a>
    </div>

    <div class="ticket-container">
        <div class="header-title">
            COMANDA DE COCINA
        </div>

        @if($isReprint)
            <div class="reprint-badge">
                *** REIMPRESIÓN ***
            </div>
        @endif

        <div class="mesa-destacada">
            {{ $order->table ? $order->table->name : 'PARA LLEVAR' }}
        </div>

        <div class="info-section">
            <div class="info-row">
                <span>Comanda:</span>
                <strong>{{ $order->order_number }}</strong>
            </div>
            <div class="info-row">
                <span>Mesero:</span>
                <strong>{{ $order->user->name ?? 'N/A' }}</strong>
            </div>
            <div class="info-row">
                <span>Fecha/Hora:</span>
                <strong>{{ now()->format('d/m/Y H:i:s') }}</strong>
            </div>
            @if($order->customer_name)
                <div class="info-row">
                    <span>Cliente:</span>
                    <strong>{{ $order->customer_name }}</strong>
                </div>
            @endif
        </div>

        <div class="items-list">
            @forelse($itemsToPrint as $item)
                <div class="item-block">
                    <div class="item-main">
                        <span class="item-qty">[{{ (float) $item->quantity == (int) $item->quantity ? (int) $item->quantity : $item->quantity }}]</span>
                        <span class="item-name">{{ $item->product->name }}</span>
                    </div>
                    @if($item->notes)
                        <div class="item-notes">
                            *** {{ $item->notes }} ***
                        </div>
                    @endif
                </div>
            @empty
                <div style="text-align: center; padding: 10px;">
                    No hay platos pendientes para cocina.
                </div>
            @endforelse
        </div>

        <div class="footer-note">
            PUNTO DE PREPARACIÓN / COCINA
        </div>
    </div>

    <script>
        window.addEventListener('load', function () {
            // Auto impresión opcional
            // window.print();
        });
    </script>
</body>
</html>
