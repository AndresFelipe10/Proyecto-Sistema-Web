<?php

namespace App\Enums;

enum ExpenseCategory: string
{
    case Merchandise = 'merchandise';
    case Utilities = 'utilities';
    case Rent = 'rent';
    case Supplies = 'supplies';
    case Payroll = 'payroll';
    case Other = 'other';

    /**
     * Get human-readable label for the expense category.
     */
    public function label(): string
    {
        return match ($this) {
            self::Merchandise => 'Mercancía / Inventario',
            self::Utilities => 'Servicios públicos',
            self::Rent => 'Arriendo / Alquiler',
            self::Supplies => 'Insumos y papelería',
            self::Payroll => 'Nómina / Salarios',
            self::Other => 'Otro',
        };
    }

    /**
     * Get all supported values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
