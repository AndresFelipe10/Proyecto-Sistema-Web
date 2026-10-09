<?php

namespace App\Services\Restaurant;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RestaurantOrderService
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    /**
     * Open a new order for a table.
     */
    public function openTableOrder(int $businessId, int $userId, array $data): RestaurantOrder
    {
        return DB::transaction(function () use ($businessId, $userId, $data) {
            $table = RestaurantTable::where('business_id', $businessId)
                ->lockForUpdate()
                ->findOrFail($data['table_id']);

            if ($table->status !== 'available') {
                throw ValidationException::withMessages([
                    'table_id' => 'La mesa seleccionada ya se encuentra ocupada.',
                ]);
            }

            $orderNumber = $this->generateOrderNumber($businessId);

            $order = RestaurantOrder::create([
                'business_id' => $businessId,
                'table_id' => $table->id,
                'user_id' => $userId,
                'order_number' => $orderNumber,
                'order_type' => 'table',
                'status' => 'open',
                'customer_name' => $data['customer_name'] ?? null,
                'guest_count' => ! empty($data['guest_count']) ? (int) $data['guest_count'] : null,
                'notes' => $data['notes'] ?? null,
                'subtotal' => 0.00,
                'total' => 0.00,
            ]);

            $table->update(['status' => 'occupied']);

            if (! empty($data['items']) && is_array($data['items'])) {
                $this->addItemsToOrder($order, $data['items']);
            }

            return $order->fresh(['table', 'items.product', 'user']);
        });
    }

    /**
     * Add dishes or products to an active order.
     *
     * @return Collection<int, RestaurantOrderItem>
     */
    public function addItemsToOrder(RestaurantOrder $order, array $itemsData): Collection
    {
        return DB::transaction(function () use ($order, $itemsData) {
            // Determinar tanda (batch_number)
            $hasPrintedItems = $order->items()
                ->where('printed_to_kitchen', true)
                ->where('status', '!=', 'cancelled')
                ->exists();

            $maxBatch = (int) ($order->items()->max('batch_number') ?? 0);

            if ($hasPrintedItems) {
                $batchNumber = max(1, $maxBatch + 1);
            } else {
                $batchNumber = max(1, $maxBatch);
            }

            $createdItems = new Collection();

            foreach ($itemsData as $itemData) {
                $product = Product::where('business_id', $order->business_id)
                    ->findOrFail($itemData['product_id']);

                $quantity = round((float) $itemData['quantity'], 3);
                $unitPrice = round((float) $product->sale_price, 2);
                $subtotal = round($quantity * $unitPrice, 2);

                $orderItem = RestaurantOrderItem::create([
                    'business_id' => $order->business_id,
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                    'notes' => ! empty($itemData['notes']) ? trim($itemData['notes']) : null,
                    'status' => 'pending',
                    'printed_to_kitchen' => false,
                    'batch_number' => $batchNumber,
                ]);

                $createdItems->push($orderItem);
            }

            $order->recalculateTotals();

            return $createdItems;
        });
    }

    /**
     * Mark pending items as printed to kitchen and advance status.
     *
     * @return Collection<int, RestaurantOrderItem>
     */
    public function sendToKitchen(RestaurantOrder $order): Collection
    {
        return DB::transaction(function () use ($order) {
            $pendingItems = $order->items()
                ->where('printed_to_kitchen', false)
                ->where('status', '!=', 'cancelled')
                ->with('product')
                ->lockForUpdate()
                ->get();

            if ($pendingItems->isNotEmpty()) {
                foreach ($pendingItems as $item) {
                    $item->update([
                        'printed_to_kitchen' => true,
                        'status' => 'kitchen',
                    ]);
                }

                if ($order->status === 'open') {
                    $order->update(['status' => 'in_kitchen']);
                }
            }

            return $pendingItems;
        });
    }

    /**
     * Open a new delivery or takeout order.
     */
    public function openDeliveryOrTakeoutOrder(int $businessId, int $userId, array $data): RestaurantOrder
    {
        return DB::transaction(function () use ($businessId, $userId, $data) {
            $orderType = $data['order_type'] ?? 'delivery';
            $deliveryFee = $orderType === 'takeout' ? 0.00 : round((float) ($data['delivery_fee'] ?? 0.00), 2);

            if ($deliveryFee < 0) {
                throw ValidationException::withMessages([
                    'delivery_fee' => 'El costo de envío no puede ser negativo.',
                ]);
            }

            $customerName = $data['customer_name'] ?? null;
            $deliveryPhone = $data['delivery_phone'] ?? null;
            $deliveryAddress = $data['delivery_address'] ?? null;

            if (! empty($data['customer_id'])) {
                $customer = \App\Models\Customer::where('business_id', $businessId)->find($data['customer_id']);
                if ($customer) {
                    $customerName = $customerName ?: $customer->name;
                    $deliveryPhone = $deliveryPhone ?: $customer->phone;
                    $deliveryAddress = $deliveryAddress ?: $customer->address;
                }
            }

            $orderNumber = $this->generateOrderNumber($businessId);

            $order = RestaurantOrder::create([
                'business_id' => $businessId,
                'table_id' => null,
                'user_id' => $userId,
                'order_number' => $orderNumber,
                'order_type' => $orderType,
                'status' => 'open',
                'customer_id' => $data['customer_id'] ?? null,
                'customer_name' => $customerName,
                'delivery_phone' => $deliveryPhone,
                'delivery_address' => $deliveryAddress,
                'delivery_notes' => $data['delivery_notes'] ?? null,
                'delivery_fee' => $deliveryFee,
                'notes' => $data['notes'] ?? null,
                'subtotal' => 0.00,
                'total' => $deliveryFee,
            ]);

            if (! empty($data['items']) && is_array($data['items'])) {
                $this->addItemsToOrder($order, $data['items']);
            }

            return $order->fresh(['items.product', 'user', 'customer']);
        });
    }

    /**
     * Update delivery operational status (in_kitchen, dispatched, delivered, cancelled).
     */
    public function updateDeliveryStatus(RestaurantOrder $order, string $newStatus): RestaurantOrder
    {
        return DB::transaction(function () use ($order, $newStatus) {
            $validStatuses = ['open', 'in_kitchen', 'dispatched', 'delivered', 'cancelled'];
            if (! in_array($newStatus, $validStatuses, true)) {
                throw ValidationException::withMessages([
                    'status' => 'Estado de pedido no válido.',
                ]);
            }

            $order->update(['status' => $newStatus]);

            return $order->fresh();
        });
    }

    /**
     * Cancel an empty order and release its assigned table.
     */
    public function cancelEmptyOrder(RestaurantOrder $order): RestaurantOrder
    {
        return DB::transaction(function () use ($order) {
            if ($order->status !== 'open') {
                throw ValidationException::withMessages([
                    'order' => 'Solo se pueden cancelar comandas en estado abierto.',
                ]);
            }

            if ($order->items()->count() > 0) {
                throw ValidationException::withMessages([
                    'order' => 'No se puede cancelar una comanda que contiene platos registrados. Anule o elimine los consumos primero.',
                ]);
            }

            $order->update(['status' => 'cancelled']);

            if ($order->table_id && $order->table) {
                $order->table->update(['status' => 'available']);
            }

            return $order->fresh();
        });
    }

    /**
     * Remove an individual item or reduce its quantity in an active order, restore stock, and recalculate totals.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function removeItemFromOrder(
        RestaurantOrder $order,
        RestaurantOrderItem $item,
        string $reason,
        User $admin,
        ?int $quantityToRemove = null
    ): RestaurantOrder {
        if (! $admin->isCurrentAdmin()) {
            throw new AuthorizationException('Esta acción es de acceso exclusivo para administradores.');
        }

        if (! $order->canBeModified()) {
            throw ValidationException::withMessages([
                'order' => 'No se pueden eliminar platos de una comanda que ya fue cobrada, facturada o anulada.',
            ]);
        }

        if ((int) $item->order_id !== (int) $order->id) {
            throw ValidationException::withMessages([
                'item' => 'El plato no pertenece a la comanda especificada.',
            ]);
        }

        if ($item->isCancelled()) {
            throw ValidationException::withMessages([
                'item' => 'Este ítem ya ha sido cancelado previamente.',
            ]);
        }

        return DB::transaction(function () use ($order, $item, $reason, $admin, $quantityToRemove) {
            $lockedItem = RestaurantOrderItem::lockForUpdate()->findOrFail($item->id);
            $lockedOrder = RestaurantOrder::lockForUpdate()->findOrFail($order->id);

            $currentQty = (int) $lockedItem->quantity;
            $qtyToRemove = $quantityToRemove !== null ? (int) $quantityToRemove : $currentQty;

            if ($qtyToRemove <= 0) {
                throw ValidationException::withMessages([
                    'quantity_to_remove' => 'La cantidad a retirar debe ser al menos 1.',
                ]);
            }

            if ($qtyToRemove > $currentQty) {
                throw ValidationException::withMessages([
                    'quantity_to_remove' => "La cantidad a retirar ({$qtyToRemove}) no puede ser mayor a la cantidad actual del plato ({$currentQty}).",
                ]);
            }

            // 1. Revertir inventario ÚNICAMENTE de la cantidad retirada con trazabilidad
            $product = Product::lockForUpdate()->find($lockedItem->product_id);
            if ($product) {
                $movementReason = $qtyToRemove >= $currentQty
                    ? "Cancelación total de ítem en comanda #{$lockedOrder->order_number}: {$reason}"
                    : "Reducción parcial de ítem (-{$qtyToRemove} un.) en comanda #{$lockedOrder->order_number}: {$reason}";

                $this->inventoryService->registerMovement(
                    product: $product,
                    type: InventoryMovement::TYPE_ENTRY,
                    quantity: $qtyToRemove,
                    reason: $movementReason,
                    userId: $admin->id,
                );
            }

            // 2. Eliminación Total vs Reducción Parcial
            if ($qtyToRemove >= $currentQty) {
                $lockedItem->update([
                    'status' => 'cancelled',
                    'cancelled_by' => $admin->id,
                    'cancelled_at' => now(),
                    'cancellation_reason' => $reason,
                ]);
            } else {
                $newQty = $currentQty - $qtyToRemove;
                $newSubtotal = round($newQty * (float) $lockedItem->unit_price, 2);

                $auditNote = "Reducción de {$qtyToRemove} un. por {$admin->name}: {$reason}";
                $existingNotes = $lockedItem->notes ? trim($lockedItem->notes) . " | " : '';
                $updatedNotes = mb_substr($existingNotes . $auditNote, 0, 255);

                $lockedItem->update([
                    'quantity' => $newQty,
                    'subtotal' => $newSubtotal,
                    'notes' => $updatedNotes,
                ]);
            }

            // 3. Recalcular la comanda
            $lockedOrder->recalculateTotals();

            return $lockedOrder->fresh(['items.product', 'table', 'user', 'customer']);
        });
    }

    /**
     * Annul an active order, release its table, restore stock for all active items, and log audit.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function cancelOrder(RestaurantOrder $order, string $reason, User $admin): RestaurantOrder
    {
        if (! $admin->isCurrentAdmin()) {
            throw new AuthorizationException('Esta acción es de acceso exclusivo para administradores.');
        }

        if (! $order->canBeModified()) {
            throw ValidationException::withMessages([
                'order' => 'No se puede anular una comanda que ya fue cobrada, facturada o anulada.',
            ]);
        }

        return DB::transaction(function () use ($order, $reason, $admin) {
            $lockedOrder = RestaurantOrder::lockForUpdate()->findOrFail($order->id);

            // Revertir inventario de todos los ítems activos y marcarlos como anulados
            $activeItems = $lockedOrder->items()
                ->where('status', '!=', 'cancelled')
                ->lockForUpdate()
                ->get();

            foreach ($activeItems as $activeItem) {
                $product = Product::lockForUpdate()->find($activeItem->product_id);
                if ($product) {
                    $this->inventoryService->registerMovement(
                        product: $product,
                        type: InventoryMovement::TYPE_ENTRY,
                        quantity: $activeItem->quantity,
                        reason: "Anulación de comanda #{$lockedOrder->order_number}: {$reason}",
                        userId: $admin->id,
                    );
                }

                $activeItem->update([
                    'status' => 'cancelled',
                    'cancelled_by' => $admin->id,
                    'cancelled_at' => now(),
                    'cancellation_reason' => $reason,
                ]);
            }

            // Liberar la mesa asociada si existe
            if ($lockedOrder->table_id) {
                $table = RestaurantTable::where('business_id', $lockedOrder->business_id)
                    ->lockForUpdate()
                    ->find($lockedOrder->table_id);

                if ($table) {
                    $table->update(['status' => 'available']);
                }
            }

            $lockedOrder->update([
                'status' => 'cancelled',
                'cancelled_by' => $admin->id,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            return $lockedOrder->fresh(['items.product', 'table', 'user', 'customer']);
        });
    }

    /**
     * Change the assigned table of an active order atomically.
     *
     * @throws ValidationException
     */
    public function changeTable(RestaurantOrder $order, RestaurantTable $newTable, User $user): RestaurantOrder
    {
        if ((int) $order->business_id !== (int) $newTable->business_id) {
            throw ValidationException::withMessages([
                'table_id' => 'La mesa seleccionada no pertenece al mismo establecimiento que la comanda.',
            ]);
        }

        if (! $order->canBeModified() || $order->status === 'billed') {
            throw ValidationException::withMessages([
                'order' => 'No se puede cambiar de mesa una comanda que ya fue cobrada, facturada o anulada.',
            ]);
        }

        if ((int) $newTable->id === (int) $order->table_id) {
            throw ValidationException::withMessages([
                'table_id' => 'La comanda ya se encuentra asignada a esta mesa.',
            ]);
        }

        if ($newTable->status !== 'available') {
            throw ValidationException::withMessages([
                'table_id' => 'La mesa seleccionada ya se encuentra ocupada.',
            ]);
        }

        return DB::transaction(function () use ($order, $newTable, $user) {
            $lockedOrder = RestaurantOrder::where('business_id', $order->business_id)
                ->lockForUpdate()
                ->findOrFail($order->id);

            if (! $lockedOrder->canBeModified() || $lockedOrder->status === 'billed') {
                throw ValidationException::withMessages([
                    'order' => 'No se puede cambiar de mesa una comanda que ya fue cobrada, facturada o anulada.',
                ]);
            }

            if ((int) $lockedOrder->table_id === (int) $newTable->id) {
                throw ValidationException::withMessages([
                    'table_id' => 'La comanda ya se encuentra asignada a esta mesa.',
                ]);
            }

            $lockedNewTable = RestaurantTable::where('business_id', $lockedOrder->business_id)
                ->lockForUpdate()
                ->findOrFail($newTable->id);

            if ($lockedNewTable->status !== 'available') {
                throw ValidationException::withMessages([
                    'table_id' => 'La mesa seleccionada ya se encuentra ocupada.',
                ]);
            }

            $oldTable = null;
            if ($lockedOrder->table_id) {
                $oldTable = RestaurantTable::where('business_id', $lockedOrder->business_id)
                    ->lockForUpdate()
                    ->find($lockedOrder->table_id);
            }

            // 1. Liberar la mesa previa
            if ($oldTable) {
                $oldTable->update(['status' => 'available']);
            }

            // 2. Ocupar la mesa destino
            $lockedNewTable->update(['status' => 'occupied']);

            // 3. Trazabilidad y auditoría
            $oldTableName = $oldTable ? $oldTable->name : 'Sin mesa previa';
            $timestamp = now('America/Bogota')->format('d/m/Y H:i');
            $auditNote = "Cambio de mesa realizado por {$user->name} el {$timestamp}: de '{$oldTableName}' a '{$lockedNewTable->name}'.";
            $existingNotes = $lockedOrder->notes ? trim($lockedOrder->notes) . "\n" : '';
            $updatedNotes = $existingNotes . "[AUDITORÍA] " . $auditNote;

            // 4. Actualizar la orden
            $lockedOrder->update([
                'table_id' => $lockedNewTable->id,
                'notes' => $updatedNotes,
            ]);

            return $lockedOrder->fresh(['table', 'items.product', 'user', 'customer']);
        });
    }

    /**
     * Generate sequential order number per business.
     * Format: ORD-XXXX (e.g. ORD-0001)
     */
    protected function generateOrderNumber(int $businessId): string
    {
        $prefix = 'ORD-';

        $lastOrder = RestaurantOrder::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('order_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if ($lastOrder && preg_match('/ORD-(\d+)/', $lastOrder->order_number, $matches)) {
            $nextNumber = ((int) $matches[1]) + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
