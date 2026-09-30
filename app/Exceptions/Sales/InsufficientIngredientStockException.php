<?php

namespace App\Exceptions\Sales;

use App\Exceptions\InsufficientStockException;

class InsufficientIngredientStockException extends InsufficientStockException
{
    protected string $ingredientName;
    protected string $unit;

    public function __construct(
        string $ingredientName,
        float|int $availableStock,
        float|int $requestedQuantity,
        string $unit = 'unidades'
    ) {
        $this->ingredientName = $ingredientName;
        $this->unit = $unit;

        $formattedAvail = (float)$availableStock == (int)$availableStock ? (int)$availableStock : $availableStock;
        $formattedReq = (float)$requestedQuantity == (int)$requestedQuantity ? (int)$requestedQuantity : $requestedQuantity;

        $message = "Stock insuficiente del insumo '{$ingredientName}'. Existencias disponibles: {$formattedAvail} {$unit}, Cantidad requerida: {$formattedReq} {$unit}.";

        parent::__construct($message, $availableStock, $requestedQuantity);
    }

    public function getIngredientName(): string
    {
        return $this->ingredientName;
    }

    public function getUnit(): string
    {
        return $this->unit;
    }
}
