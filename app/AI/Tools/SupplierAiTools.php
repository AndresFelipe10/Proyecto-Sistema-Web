<?php

namespace App\AI\Tools;

use App\AI\DTOs\AiQueryResult;
use App\Models\Supplier;

class SupplierAiTools
{
    /**
     * List active suppliers for the specified tenant (read-only).
     */
    public function list(int $businessId, array $filters = []): AiQueryResult
    {
        $query = Supplier::where('business_id', $businessId)
            ->where('is_active', true);

        if (!empty($filters['search']) && is_string($filters['search'])) {
            $escaped = addcslashes(trim($filters['search']), '%_\\');
            $query->where(function ($q) use ($escaped) {
                $q->where('name', 'like', "%{$escaped}%")
                  ->orWhere('identification_number', 'like', "%{$escaped}%")
                  ->orWhere('contact_name', 'like', "%{$escaped}%");
            });
        }

        $count = $query->count();

        if ($count === 0) {
            return new AiQueryResult(
                intent: 'list_suppliers',
                summary: 'No se encontraron proveedores activos registrados en el negocio.',
                data: [],
                meta: ['domain' => 'suppliers', 'count' => 0]
            );
        }

        $suppliers = (clone $query)->orderBy('name')->limit(15)->get();

        $data = $suppliers->map(fn($s) => [
            'name' => $s->name,
            'document' => $s->identification_number ?? 'N/A',
            'contact_name' => $s->contact_name ?? 'N/A',
            'phone' => $s->phone ?? 'N/A',
            'email' => $s->email ?? 'N/A',
        ])->toArray();

        return new AiQueryResult(
            intent: 'list_suppliers',
            summary: "Se encontraron {$count} proveedores registrados en tu negocio.",
            data: $data,
            meta: [
                'domain' => 'suppliers',
                'count' => $count,
            ]
        );
    }
}
