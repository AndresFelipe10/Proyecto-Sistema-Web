<?php

namespace App\AI\Enums;

class AiIntent
{
    public const LIST_CUSTOMERS = 'list_customers';
    public const COUNT_CUSTOMERS = 'count_customers';
    public const LIST_PRODUCTS = 'list_products';
    public const LOW_STOCK_PRODUCTS = 'low_stock_products';
    public const OUT_OF_STOCK_PRODUCTS = 'out_of_stock_products';
    public const TOP_SELLING_PRODUCTS = 'top_selling_products';
    public const SALES_SUMMARY = 'sales_summary';
    public const SALES_BY_PERIOD = 'sales_by_period';
    public const INVENTORY_SUMMARY = 'inventory_summary';

    /**
     * Complete immutable whitelist of authorized intents.
     */
    public const ALL = [
        self::LIST_CUSTOMERS,
        self::COUNT_CUSTOMERS,
        self::LIST_PRODUCTS,
        self::LOW_STOCK_PRODUCTS,
        self::OUT_OF_STOCK_PRODUCTS,
        self::TOP_SELLING_PRODUCTS,
        self::SALES_SUMMARY,
        self::SALES_BY_PERIOD,
        self::INVENTORY_SUMMARY,
    ];

    /**
     * Check if an intent is in the authorized whitelist.
     */
    public static function isValid(?string $intent): bool
    {
        return in_array($intent, self::ALL, true);
    }

    /**
     * Function declarations and schemas for LLM tool use / function calling.
     */
    public static function getFunctionDeclarations(): array
    {
        return [
            [
                'name' => self::LIST_CUSTOMERS,
                'description' => 'Listar clientes registrados en el negocio. Permite buscar por nombre.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'search' => ['type' => 'STRING', 'description' => 'Término de búsqueda de cliente (opcional)'],
                    ],
                ],
            ],
            [
                'name' => self::COUNT_CUSTOMERS,
                'description' => 'Obtener la cantidad total de clientes registrados en el emprendimiento.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => (object)[],
                ],
            ],
            [
                'name' => self::LIST_PRODUCTS,
                'description' => 'Listar productos del catálogo, precios y stock.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'search' => ['type' => 'STRING', 'description' => 'Buscar por nombre o SKU de producto (opcional)'],
                    ],
                ],
            ],
            [
                'name' => self::LOW_STOCK_PRODUCTS,
                'description' => 'Listar productos que tienen stock bajo o igual al stock mínimo de seguridad.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => (object)[],
                ],
            ],
            [
                'name' => self::OUT_OF_STOCK_PRODUCTS,
                'description' => 'Listar productos que están completamente agotados (stock 0 o menor).',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => (object)[],
                ],
            ],
            [
                'name' => self::TOP_SELLING_PRODUCTS,
                'description' => 'Obtener el ranking de los productos más vendidos y su volumen.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'limit' => ['type' => 'INTEGER', 'description' => 'Cantidad de productos en el top (por defecto 5)'],
                    ],
                ],
            ],
            [
                'name' => self::SALES_SUMMARY,
                'description' => 'Obtener el resumen financiero de ventas de hoy o del mes actual.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'period' => [
                            'type' => 'STRING',
                            'description' => 'Período: "today" para ventas de hoy o "month" para el mes actual',
                        ],
                    ],
                ],
            ],
            [
                'name' => self::SALES_BY_PERIOD,
                'description' => 'Consultar ventas en un rango de fechas o período específico.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'date_from' => ['type' => 'STRING', 'description' => 'Fecha inicio (YYYY-MM-DD)'],
                        'date_to' => ['type' => 'STRING', 'description' => 'Fecha fin (YYYY-MM-DD)'],
                    ],
                ],
            ],
            [
                'name' => self::INVENTORY_SUMMARY,
                'description' => 'Obtener el valor total del inventario al costo, precio de venta y total de unidades.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => (object)[],
                ],
            ],
        ];
    }
}
