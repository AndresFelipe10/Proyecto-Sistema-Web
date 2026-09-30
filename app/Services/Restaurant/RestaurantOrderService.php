<?php

namespace App\Services\Restaurant;

use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\RestaurantTable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RestaurantOrderService
{
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
