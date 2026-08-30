<?php

namespace App\Exceptions;

use Exception;

class InsufficientStockException extends Exception
{
    protected int $availableStock;
    protected int $requestedQuantity;

    public function __construct(
        string $message = 'Stock insuficiente para realizar la operación.',
        int $availableStock = 0,
        int $requestedQuantity = 0,
        int $code = 0,
        ?Exception $previous = null
    ) {
        $this->availableStock = $availableStock;
        $this->requestedQuantity = $requestedQuantity;

        if ($availableStock > 0 || $requestedQuantity > 0) {
            $message = "Stock insuficiente. Existencias disponibles: {$availableStock}, Cantidad solicitada: {$requestedQuantity}.";
        }

        parent::__construct($message, $code, $previous);
    }

    public function getAvailableStock(): int
    {
        return $this->availableStock;
    }

    public function getRequestedQuantity(): int
    {
        return $this->requestedQuantity;
    }
}
