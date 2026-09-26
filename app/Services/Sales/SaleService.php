<?php

namespace App\Services\Sales;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\Sales\InvalidSaleItemException;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
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
            $total = $subtotal - $discount;

            // Generate unique invoice number for this business
            $invoiceNumber = $this->generateInvoiceNumber($businessId);

            // Create sale header
            $sale = Sale::create([
                'business_id' => $businessId,
                'user_id' => $userId,
                'customer_id' => $data['customer_id'] ?? null,
                'invoice_number' => $invoiceNumber,
                'sale_date' => $data['sale_date'],
                'subtotal' => $subtotal,
                'discount' => $discount,
                'discount_percentage' => $discountPercentage,
                'total' => $total,
                'payment_method' => $data['payment_method'],
                'status' => 'completed',
                'notes' => $data['notes'] ?? null,
            ]);

            // Create detail lines and decrement stock
            foreach ($resolvedItems as $resolved) {
                SaleDetail::create([
                    'sale_id' => $sale->id,
                    'product_id' => $resolved['product']->id,
                    'quantity' => $resolved['quantity'],
                    'unit_price' => $resolved['unit_price'],
                    'subtotal' => $resolved['subtotal'],
                ]);

                // Decrement stock via InventoryService (pessimistic locking)
                $this->inventoryService->registerMovement(
                    product: $resolved['product'],
                    type: InventoryMovement::TYPE_EXIT,
                    quantity: $resolved['quantity'],
                    reason: "Venta #{$invoiceNumber}",
                    userId: $userId,
                    saleId: $sale->id,
                );
            }

            return $sale->load('details.product', 'customer', 'user');
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
                $this->inventoryService->registerMovement(
                    product: $detail->product_id,
                    type: InventoryMovement::TYPE_ENTRY,
                    quantity: $detail->quantity,
                    reason: "Anulación venta #{$sale->invoice_number}",
                    userId: $userId,
                    saleId: $sale->id,
                );
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
