<?php

namespace App\Services\Inventory;

use App\Exceptions\InsufficientStockException;
use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Register an inventory movement within a database transaction using pessimistic locking.
     *
     * @throws InsufficientStockException
     */
    public function registerMovement(
        int|Product $product,
        string $type,
        int $quantity,
        ?string $reason = null,
        ?int $userId = null,
        ?int $saleId = null
    ): InventoryMovement {
        return DB::transaction(function () use ($product, $type, $quantity, $reason, $userId, $saleId) {
            $productId = $product instanceof Product ? $product->id : $product;

            // Bloqueo pesimista para evitar condiciones de carrera (ej. stock=1, dos ventas concurrentes)
            $lockedProduct = Product::lockForUpdate()->findOrFail($productId);
            $previousStock = (int) $lockedProduct->stock;

            $movementQuantity = $quantity;

            switch ($type) {
                case InventoryMovement::TYPE_ENTRY:
                    $newStock = $previousStock + $quantity;
                    break;

                case InventoryMovement::TYPE_EXIT:
                    if ($previousStock < $quantity) {
                        throw new InsufficientStockException(
                            availableStock: $previousStock,
                            requestedQuantity: $quantity
                        );
                    }
                    $newStock = $previousStock - $quantity;
                    break;

                case InventoryMovement::TYPE_ADJUSTMENT:
                    $newStock = $quantity;
                    $movementQuantity = abs($newStock - $previousStock);
                    break;

                default:
                    throw new \InvalidArgumentException("Tipo de movimiento de inventario no válido: {$type}");
            }

            // Actualizar stock del producto
            $lockedProduct->update([
                'stock' => $newStock,
            ]);

            // Registrar movimiento de trazabilidad
            return InventoryMovement::create([
                'business_id' => $lockedProduct->business_id,
                'product_id' => $lockedProduct->id,
                'user_id' => $userId ?? auth()->id(),
                'sale_id' => $saleId,
                'type' => $type,
                'quantity' => $movementQuantity,
                'previous_stock' => $previousStock,
                'new_stock' => $newStock,
                'reason' => $reason,
                'movement_date' => now(),
            ]);
        });
    }
}
