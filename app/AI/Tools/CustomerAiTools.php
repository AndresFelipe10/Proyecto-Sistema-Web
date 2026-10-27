<?php

namespace App\AI\Tools;

use App\AI\DTOs\AiQueryResult;
use App\Models\Customer;

class CustomerAiTools
{
    /**
     * List customers for the specified tenant (read-only).
     */
    public function list(int $businessId, array $filters = []): AiQueryResult
    {
        $query = Customer::where('business_id', $businessId)->where('is_active', true);

        if (!empty($filters['search']) && is_string($filters['search'])) {
            $escaped = addcslashes(trim($filters['search']), '%_\\');
            $query->where(function ($q) use ($escaped) {
                $q->where('name', 'like', "%{$escaped}%")
                  ->orWhere('email', 'like', "%{$escaped}%")
                  ->orWhere('phone', 'like', "%{$escaped}%")
                  ->orWhere('document', 'like', "%{$escaped}%");
            });
        }

        $totalCount = $query->count();

        if ($totalCount === 0) {
            return new AiQueryResult(
                intent: 'list_customers',
                summary: "No se encontraron clientes activos registrados en el emprendimiento.",
                data: []
            );
        }

        $customers = (clone $query)->orderBy('name')->limit(15)->get();

        $data = $customers->map(fn($c) => [
            'name' => $c->name,
            'document' => $c->document ?? 'N/A',
            'phone' => $c->phone ?? 'N/A',
            'email' => $c->email ?? 'N/A',
        ])->toArray();

        return new AiQueryResult(
            intent: 'list_customers',
            summary: "Se encontraron {$totalCount} clientes activos en tu emprendimiento.",
            data: $data,
            meta: [
                'domain' => 'customers',
                'count' => $totalCount,
            ]
        );
    }

    /**
     * Count customers for the specified tenant (read-only).
     */
    public function count(int $businessId): AiQueryResult
    {
        $customers = Customer::where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $total = $customers->count();

        // Enriquecer con el listado si la cantidad es pequeña (<= 10 clientes)
        $data = [];
        if ($total > 0 && $total <= 10) {
            $data = $customers->map(fn($c) => [
                'name' => $c->name,
                'document' => $c->document ?? 'N/A',
                'phone' => $c->phone ?? 'N/A',
                'email' => $c->email ?? 'N/A',
            ])->toArray();
        } else {
            $data = ['total_customers' => $total];
        }

        return new AiQueryResult(
            intent: 'count_customers',
            summary: "Actualmente tienes un total de {$total} clientes activos registrados.",
            data: $data,
            meta: [
                'domain' => 'customers',
                'total_customers' => $total,
            ]
        );
    }
}
