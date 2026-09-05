<?php

namespace App\Services\Sales;

use App\Exceptions\InsufficientStockException;
use App\Models\InventoryMovement;
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
     * Creates the sale header, detail lines, and decrements stock for each product
     * via InventoryService (pessimistic locking). If any product lacks stock,
     * InsufficientStockException propagates and the entire transaction rolls back.
     *
     * @param  array  $data       Validated request data
     * @param  int    $businessId Active tenant ID
     * @param  int    $userId     Authenticated user ID
     * @return Sale               The created sale with details loaded
     *
     * @throws InsufficientStockException
     */
    public function processSale(array $data, int $businessId, int $userId): Sale
    {
        return DB::transaction(function () use ($data, $businessId, $userId) {
            // Calculate subtotal from line items
            $subtotal = 0;
            foreach ($data['items'] as $item) {
                $subtotal += $item['quantity'] * $item['unit_price'];
            }

            $discount = (float) ($data['discount'] ?? 0);
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
                'total' => $total,
                'payment_method' => $data['payment_method'],
                'status' => 'completed',
                'notes' => $data['notes'] ?? null,
            ]);

            // Create detail lines and decrement stock
            foreach ($data['items'] as $item) {
                $lineSubtotal = $item['quantity'] * $item['unit_price'];

                SaleDetail::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $lineSubtotal,
                ]);

                // Decrement stock via InventoryService (pessimistic locking)
                $this->inventoryService->registerMovement(
                    product: (int) $item['product_id'],
                    type: InventoryMovement::TYPE_EXIT,
                    quantity: (int) $item['quantity'],
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
