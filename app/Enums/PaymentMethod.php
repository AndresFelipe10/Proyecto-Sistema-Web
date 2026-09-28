<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case Transfer = 'transfer';
    case Other = 'other';

    /**
     * Get human-readable label for the payment method.
     */
    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Efectivo',
            self::Card => 'Tarjeta débito/crédito',
            self::Transfer => 'Transferencia / Nequi / Daviplata',
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
