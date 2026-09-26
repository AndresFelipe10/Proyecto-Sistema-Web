<?php

namespace App\Exceptions\Sales;

use Exception;

class InvalidSaleItemException extends Exception
{
    public function __construct(
        string $message = 'El producto seleccionado no está disponible en este negocio o ya no existe.',
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
