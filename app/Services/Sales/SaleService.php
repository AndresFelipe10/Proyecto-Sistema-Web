<?php

namespace App\Services\Sales;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\Sales\InsufficientIngredientStockException;
use App\Exceptions\Sales\InvalidSaleItemException;
use App\Exceptions\Sales\InvalidSalePaymentException;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\SalePayment;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;

class SaleService
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    /**
     * Process a complete sale within a single database transaction.
     *
     * Resolves authoritative prices directly from products.sale_price under
     * pessimistic locking (lockForUpdate). Client-sent unit prices are strictly ignored.
     * Creates the sale header, detail lines, and decrements stock for each product
     * via InventoryService. If any product lacks stock, InsufficientStockException
     * propagates and the entire transaction rolls back.
     *
     * @param  array  $data       Validated request data
     * @param  int    $businessId Active tenant ID
     * @param  int    $userId     Authenticated user ID
     * @return Sale               The created sale with details loaded
     *
     * @throws InsufficientStockException
     * @throws InvalidSaleItemException
     */
    public function processSale(array $data, int $businessId, int $userId): Sale
    {
        return DB::transaction(function () use ($data, $businessId, $userId) {
            $subtotal = 0;
            $resolvedItems = [];

            // Lock products and resolve authoritative pricing from the database
            foreach ($data['items'] as $item) {
                $product = Product::withoutGlobalScopes()
                    ->where('business_id', $businessId)
                    ->lockForUpdate()
                    ->find($item['product_id']);

                if ($product === null) {
                    throw new InvalidSaleItemException(
                        'El producto seleccionado no está disponible en este negocio o ya no existe.'
                    );
                }

                $unitPrice = (float) $product->sale_price;
                $quantity = (int) $item['quantity'];
                $lineSubtotal = $quantity * $unitPrice;
                $subtotal += $lineSubtotal;

                $resolvedItems[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $lineSubtotal,
                ];
            }

            // Calculate discount from percentage (never trust client-sent monetary values)
            $discountPercentage = (float) ($data['discount_percentage'] ?? 0);
            $discount = round($subtotal * ($discountPercentage / 100), 2);
            $deliveryFee = isset($data['delivery_fee']) ? max(0, round((float) $data['delivery_fee'], 2)) : 0.00;
            $total = round($subtotal - $discount + $deliveryFee, 2);

            // Generate unique invoice number for this business
            $invoiceNumber = $this->generateInvoiceNumber($businessId);

            // Resolve customer snapshot (or fallback to Consumidor Final)
            $customerId = $data['customer_id'] ?? null;
            $customerName = config('sales.default_customer_name', 'CONSUMIDOR FINAL');
            $customerDocument = config('sales.default_customer_document', '222222222222');

            if ($customerId !== null) {
                $customer = \App\Models\Customer::withoutGlobalScopes()
                    ->where('business_id', $businessId)
                    ->find($customerId);

                if ($customer) {
                    $customerName = $customer->name;
                    $customerDocument = $customer->document ?? $customer->identification_number ?? $customerDocument;
                } else {
                    $customerId = null;
                }
            }

            // Normalize payments (support both 'payments' array and legacy 'payment_method')
            $payments = [];
            if (!empty($data['payments']) && is_array($data['payments'])) {
                $payments = array_values($data['payments']);
            } elseif (!empty($data['payment_method'])) {
                $payments = [
                    [
                        'method' => $data['payment_method'],
                        'amount' => $total,
                        'reference' => null,
                        'cash_received' => $data['payment_method'] === 'cash' ? $total : null,
                        'change_given' => $data['payment_method'] === 'cash' ? 0.00 : null,
                    ],
                ];
            } else {
                $payments = [
                    [
                        'method' => 'cash',
                        'amount' => $total,
                        'reference' => null,
                        'cash_received' => $total,
                        'change_given' => 0.00,
                    ],
                ];
            }

            // Authoritative payments validation when total > 0
            if ($total > 0) {
                if (count($payments) < 1 || count($payments) > 5) {
                    throw new InvalidSalePaymentException('Debe especificar entre 1 y 5 líneas de pago.');
                }

                $cashLines = array_filter($payments, fn($p) => ($p['method'] ?? '') === 'cash');
                if (count($cashLines) > 1) {
                    throw new InvalidSalePaymentException('Solo se permite una línea de pago en efectivo.');
                }

                $totalCents = (int) round($total * 100);
                $paymentsCents = 0;

                foreach ($payments as $p) {
                    $amt = (float) ($p['amount'] ?? 0);
                    if ($amt <= 0) {
                        throw new InvalidSalePaymentException('Cada línea de pago debe tener un monto mayor a cero.');
                    }
                    if (!in_array($p['method'] ?? '', ['cash', 'card', 'transfer', 'other'])) {
                        throw new InvalidSalePaymentException("El método de pago '{$p['method']}' es inválido.");
                    }
                    $paymentsCents += (int) round($amt * 100);
                }

                if ($paymentsCents !== $totalCents) {
                    throw new InvalidSalePaymentException('La suma de los métodos de pago no coincide con el total de la venta.');
                }

                // Validar efectivo recibido
                foreach ($cashLines as $cashP) {
                    $cashAmt = (float) $cashP['amount'];
                    if (isset($cashP['cash_received']) && $cashP['cash_received'] !== '' && $cashP['cash_received'] !== null) {
                        $cashRec = (float) $cashP['cash_received'];
                        if ($cashRec < $cashAmt) {
                            throw new InvalidSalePaymentException('El efectivo recibido no puede ser menor al monto asignado en efectivo.');
                        }
                    }
                }
            }

            // Derivar payment_method para la cabecera de la venta
            if (count($payments) === 1) {
                $derivedPaymentMethod = $payments[0]['method'];
            } else {
                $derivedPaymentMethod = 'mixed';
            }

            // Create sale header
            $sale = Sale::create([
                'business_id' => $businessId,
                'user_id' => $userId,
                'customer_id' => $customerId,
                'customer_name' => $customerName,
                'customer_document' => $customerDocument,
                'restaurant_order_id' => $data['restaurant_order_id'] ?? null,
                'order_type' => $data['order_type'] ?? null,
                'invoice_number' => $invoiceNumber,
                'sale_date' => $data['sale_date'],
                'subtotal' => $subtotal,
                'discount' => $discount,
                'discount_percentage' => $discountPercentage,
                'delivery_fee' => $deliveryFee,
                'total' => $total,
                'payment_method' => $derivedPaymentMethod,
                'status' => 'completed',
                'notes' => $data['notes'] ?? null,
            ]);

            // Create sale_payments rows
            if ($total > 0) {
                foreach ($payments as $p) {
                    $method = $p['method'];
                    $amount = round((float) $p['amount'], 2);
                    $isCash = ($method === 'cash');

                    if ($isCash) {
                        $cashReceived = (isset($p['cash_received']) && $p['cash_received'] !== '' && $p['cash_received'] !== null)
                            ? round((float) $p['cash_received'], 2)
                            : $amount;
                        $changeGiven = round($cashReceived - $amount, 2);
                    } else {
                        $cashReceived = null;
                        $changeGiven = null;
                    }

                    $cleanRef = !empty($p['reference']) ? strip_tags(trim((string)$p['reference'])) : null;
                    if ($cleanRef !== null && mb_strlen($cleanRef) > 60) {
                        $cleanRef = mb_substr($cleanRef, 0, 60);
                    }

                    SalePayment::create([
                        'business_id' => $businessId,
                        'sale_id' => $sale->id,
                        'method' => $method,
                        'amount' => $amount,
                        'reference' => $cleanRef,
                        'cash_received' => $cashReceived,
                        'change_given' => $changeGiven,
                    ]);
                }
            }

            // Create detail lines and decrement stock
            foreach ($resolvedItems as $resolved) {
                SaleDetail::create([
                    'sale_id' => $sale->id,
                    'product_id' => $resolved['product']->id,
                    'quantity' => $resolved['quantity'],
                    'unit_price' => $resolved['unit_price'],
                    'subtotal' => $resolved['subtotal'],
                ]);

                if ($resolved['product']->isDish()) {
                    // Plato con receta: resolver la receta activa
                    $recipe = Recipe::withoutGlobalScopes()
                        ->where('business_id', $businessId)
                        ->where('product_id', $resolved['product']->id)
                        ->where('is_active', true)
                        ->with('items')
                        ->first();

                    if (! $recipe || $recipe->items->isEmpty()) {
                        throw new InvalidSaleItemException(
                            "El plato '{$resolved['product']->name}' no cuenta con una receta activa configurada."
                        );
                    }

                    // Ordenar insumos por ID para prevenir deadlocks
                    $sortedItems = $recipe->items->sortBy('ingredient_id');

                    foreach ($sortedItems as $recipeItem) {
                        $requiredQty = round((float) $recipeItem->quantity_per_portion * (float) $resolved['quantity'], 3);

                        // Bloqueo pesimista sobre el insumo
                        $ingredient = Product::withoutGlobalScopes()
                            ->where('business_id', $businessId)
                            ->lockForUpdate()
                            ->find($recipeItem->ingredient_id);

                        if (! $ingredient) {
                            throw new InvalidSaleItemException(
                                "El insumo #{$recipeItem->ingredient_id} de la receta no existe o fue eliminado."
                            );
                        }

                        if ((float) $ingredient->stock < $requiredQty) {
                            throw new InsufficientIngredientStockException(
                                ingredientName: $ingredient->name,
                                availableStock: (float) $ingredient->stock,
                                requestedQuantity: $requiredQty,
                                unit: $recipeItem->unit
                            );
                        }

                        $this->inventoryService->registerMovement(
                            product: $ingredient,
                            type: InventoryMovement::TYPE_EXIT,
                            quantity: $requiredQty,
                            reason: "receta_venta",
                            userId: $userId,
                            saleId: $sale->id,
                        );
                    }
                } else {
                    // Decrement stock via InventoryService (pessimistic locking) para productos estándar
                    $this->inventoryService->registerMovement(
                        product: $resolved['product'],
                        type: InventoryMovement::TYPE_EXIT,
                        quantity: $resolved['quantity'],
                        reason: "Venta #{$invoiceNumber}",
                        userId: $userId,
                        saleId: $sale->id,
                    );
                }
            }

            // Close linked restaurant order and free table if applicable
            if (! empty($data['restaurant_order_id'])) {
                $restaurantOrder = \App\Models\RestaurantOrder::withoutGlobalScopes()
                    ->where('business_id', $businessId)
                    ->lockForUpdate()
                    ->find($data['restaurant_order_id']);

                if ($restaurantOrder) {
                    $restaurantOrder->update([
                        'status' => 'closed',
                        'sale_id' => $sale->id,
                        'closed_at' => now(),
                    ]);

                    if ($restaurantOrder->table_id) {
                        $table = \App\Models\RestaurantTable::withoutGlobalScopes()
                            ->where('business_id', $businessId)
                            ->lockForUpdate()
                            ->find($restaurantOrder->table_id);

                        if ($table) {
                            $table->update(['status' => 'available']);
                        }
                    }
                }
            }

            return $sale->load('details.product', 'customer', 'user', 'payments', 'restaurantOrder');
        });
    }

    /**
     * Cancel a completed sale and restore stock for each line item.
     *
     * @param  Sale  $sale  The sale to cancel
     * @param  int   $userId  The user performing the cancellation
     * @return Sale  The updated sale
     */
    public function cancelSale(Sale $sale, int $userId): Sale
    {
        return DB::transaction(function () use ($sale, $userId) {
            $sale->load('details');

            // Restore stock for each detail line
            foreach ($sale->details as $detail) {
                $product = Product::withoutGlobalScopes()
                    ->where('business_id', $sale->business_id)
                    ->find($detail->product_id);

                if ($product && $product->isDish()) {
                    $recipe = Recipe::withoutGlobalScopes()
                        ->where('business_id', $sale->business_id)
                        ->where('product_id', $product->id)
                        ->with('items')
                        ->first();

                    if ($recipe) {
                        foreach ($recipe->items as $recipeItem) {
                            $restoreQty = round((float) $recipeItem->quantity_per_portion * (float) $detail->quantity, 3);
                            $this->inventoryService->registerMovement(
                                product: $recipeItem->ingredient_id,
                                type: InventoryMovement::TYPE_ENTRY,
                                quantity: $restoreQty,
                                reason: "receta_anulacion",
                                userId: $userId,
                                saleId: $sale->id,
                            );
                        }
                    }
                } else {
                    $this->inventoryService->registerMovement(
                        product: $detail->product_id,
                        type: InventoryMovement::TYPE_ENTRY,
                        quantity: $detail->quantity,
                        reason: "Anulación venta #{$sale->invoice_number}",
                        userId: $userId,
                        saleId: $sale->id,
                    );
                }
            }

            $sale->update(['status' => 'cancelled']);

            return $sale->fresh(['details.product', 'customer', 'user']);
        });
    }

    /**
     * Generate a sequential invoice number for the business.
     * Format: VTA-YYYYMM-XXXX (e.g. VTA-202608-0001)
     */
    protected function generateInvoiceNumber(int $businessId): string
    {
        $prefix = 'VTA-' . now()->format('Ym') . '-';

        $lastSale = Sale::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('invoice_number', 'like', $prefix . '%')
            ->orderByDesc('invoice_number')
            ->lockForUpdate()
            ->first();

        if ($lastSale) {
            $lastNumber = (int) str_replace($prefix, '', $lastSale->invoice_number);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
