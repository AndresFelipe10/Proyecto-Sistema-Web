<?php

namespace App\Exceptions\Sales;

use Exception;

class InvalidSalePaymentException extends Exception
{
    public function __construct(
        string $message = 'La distribución de pagos de la venta es inválida.',
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
