<?php

namespace App\Services\Expenses;

use App\Models\Expense;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseService
{
    protected const DISK = 'local'; // Almacenamiento privado dentro de storage/app/private

    /**
     * REGLA CONTABLE FUNDAMENTAL:
     * Registrar un gasto o factura de compra con categoría mercancía ('merchandise')
     * NO altera las existencias físicas ni el stock en el inventario.
     * La administración física de stock se gestiona de forma autónoma y trazable
     * a través del módulo de Inventario (InventoryService y movimientos de stock).
     */

    /**
     * Obtiene los gastos filtrados y paginados para el tenant activo.
     */
    public function getFilteredExpenses(int $businessId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Expense::where('business_id', $businessId)
            ->with(['supplier', 'creator']);

        $this->applyFilters($query, $filters);

        return $query->latest('issue_date')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Calcula métricas y totales consolidados según los filtros aplicados.
     */
    public function getSummaryMetrics(int $businessId, array $filters = []): array
    {
        $query = Expense::where('business_id', $businessId);
        $this->applyFilters($query, $filters);

        $expenses = $query->get();

        $totalAmount = (float) $expenses->sum('amount');
        $paidAmount = (float) $expenses->where('status', 'paid')->sum('amount');
        $pendingAmount = (float) $expenses->where('status', 'pending')->sum('amount');

        $overdueExpenses = $expenses->filter(fn(Expense $e) => $e->is_overdue);
        $overdueAmount = (float) $overdueExpenses->sum('amount');
        $overdueCount = $overdueExpenses->count();

        return [
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'pending_amount' => $pendingAmount,
            'overdue_amount' => $overdueAmount,
            'overdue_count' => $overdueCount,
            'count' => $expenses->count(),
        ];
    }

    /**
     * Registra un nuevo gasto o factura de compra.
     */
    public function createExpense(array $data, int $businessId, int $userId, ?UploadedFile $attachment = null): Expense
    {
        return DB::transaction(function () use ($data, $businessId, $userId, $attachment) {
            $attachmentData = $this->handleUpload($attachment);

            $status = $data['status'] ?? 'pending';
            $paidAt = $status === 'paid' ? ($data['paid_at'] ?? now()->toDateString()) : null;
            $paymentMethod = $status === 'paid' ? ($data['payment_method'] ?? null) : null;

            return Expense::create([
                'business_id' => $businessId,
                'supplier_id' => !empty($data['supplier_id']) ? (int) $data['supplier_id'] : null,
                'invoice_number' => !empty($data['invoice_number']) ? trim((string) $data['invoice_number']) : null,
                'issue_date' => $data['issue_date'],
                'due_date' => !empty($data['due_date']) ? $data['due_date'] : null,
                'category' => $data['category'],
                'description' => !empty($data['description']) ? trim((string) $data['description']) : null,
                'amount' => round((float) $data['amount'], 2),
                'status' => $status,
                'paid_at' => $paidAt,
                'payment_method' => $paymentMethod,
                'attachment_path' => $attachmentData['path'] ?? null,
                'attachment_original_name' => $attachmentData['original_name'] ?? null,
                'created_by' => $userId,
            ]);
        });
    }

    /**
     * Actualiza un gasto existente.
     */
    public function updateExpense(
        Expense $expense,
        array $data,
        ?UploadedFile $attachment = null,
        bool $removeAttachment = false
    ): Expense {
        return DB::transaction(function () use ($expense, $data, $attachment, $removeAttachment) {
            $status = $data['status'] ?? $expense->status;
            $paidAt = $status === 'paid' ? ($data['paid_at'] ?? $expense->paid_at ?? now()->toDateString()) : null;
            $paymentMethod = $status === 'paid' ? ($data['payment_method'] ?? $expense->payment_method) : null;

            $updatePayload = [
                'supplier_id' => !empty($data['supplier_id']) ? (int) $data['supplier_id'] : null,
                'invoice_number' => !empty($data['invoice_number']) ? trim((string) $data['invoice_number']) : null,
                'issue_date' => $data['issue_date'],
                'due_date' => !empty($data['due_date']) ? $data['due_date'] : null,
                'category' => $data['category'],
                'description' => !empty($data['description']) ? trim((string) $data['description']) : null,
                'amount' => round((float) $data['amount'], 2),
                'status' => $status,
                'paid_at' => $paidAt,
                'payment_method' => $paymentMethod,
            ];

            // Reemplazo o eliminación de archivo adjunto
            if ($attachment !== null) {
                $this->deletePhysicalAttachment($expense->attachment_path);
                $upload = $this->handleUpload($attachment);
                $updatePayload['attachment_path'] = $upload['path'];
                $updatePayload['attachment_original_name'] = $upload['original_name'];
            } elseif ($removeAttachment) {
                $this->deletePhysicalAttachment($expense->attachment_path);
                $updatePayload['attachment_path'] = null;
                $updatePayload['attachment_original_name'] = null;
            }

            $expense->update($updatePayload);

            return $expense->fresh(['supplier', 'creator']);
        });
    }

    /**
     * Elimina un gasto de forma segura (borrando físicamente el adjunto privado si existe).
     */
    public function deleteExpense(Expense $expense): bool
    {
        return DB::transaction(function () use ($expense) {
            $this->deletePhysicalAttachment($expense->attachment_path);
            return $expense->delete();
        });
    }

    /**
     * Marca un gasto pendiente como pagado.
     */
    public function markAsPaid(Expense $expense, array $data): Expense
    {
        $expense->update([
            'status' => 'paid',
            'paid_at' => $data['paid_at'] ?? now()->toDateString(),
            'payment_method' => $data['payment_method'],
        ]);

        return $expense->fresh();
    }

    /**
     * Genera una respuesta de streaming segura para descargar o previsualizar el adjunto privado.
     */
    public function getAttachmentResponse(Expense $expense): StreamedResponse
    {
        if (empty($expense->attachment_path) || !Storage::disk(self::DISK)->exists($expense->attachment_path)) {
            abort(404, 'El archivo adjunto no existe o fue eliminado.');
        }

        $filename = $expense->attachment_original_name ?: basename($expense->attachment_path);

        return Storage::disk(self::DISK)->download(
            $expense->attachment_path,
            $filename,
            [
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            ]
        );
    }

    /**
     * Maneja el almacenamiento de un archivo adjunto con nombre aleatorio y hash.
     */
    protected function handleUpload(?UploadedFile $file): ?array
    {
        if ($file === null) {
            return null;
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $hashName = Str::random(40) . '.' . $extension;
        $path = $file->storeAs('expenses', $hashName, self::DISK);

        return [
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
        ];
    }

    /**
     * Borra el archivo físico del disco privado si existe.
     */
    protected function deletePhysicalAttachment(?string $path): void
    {
        if (!empty($path) && Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    /**
     * Aplica filtros sobre el query de gastos.
     */
    protected function applyFilters($query, array $filters): void
    {
        // Filtro por proveedor
        if (!empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        // Filtro por categoría
        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        // Filtro por estado
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'vencida') {
                $query->overdue();
            } else {
                $query->where('status', $filters['status']);
            }
        }

        // Filtro por mes y año (o rango de fechas)
        if (!empty($filters['month']) && !empty($filters['year'])) {
            $query->whereMonth('issue_date', $filters['month'])
                ->whereYear('issue_date', $filters['year']);
        } elseif (!empty($filters['year'])) {
            $query->whereYear('issue_date', $filters['year']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('issue_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('issue_date', '<=', $filters['date_to']);
        }

        // Búsqueda por número de factura o descripción
        if (!empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'LIKE', $search)
                    ->orWhere('description', 'LIKE', $search);
            });
        }
    }
}
