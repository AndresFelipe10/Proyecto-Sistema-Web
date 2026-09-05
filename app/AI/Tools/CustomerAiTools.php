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

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $customers = $query->orderBy('name')->limit(15)->get();
        $count = $customers->count();

        if ($count === 0) {
            return new AiQueryResult(
                intent: 'list_customers',
                summary: "No se encontraron clientes activos registrados en el emprendimiento.",
                data: []
            );
        }

        $data = $customers->map(fn($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'email' => $c->email ?? 'N/A',
            'phone' => $c->phone ?? 'N/A',
            'city' => $c->city ?? 'Cali',
        ])->toArray();

        return new AiQueryResult(
            intent: 'list_customers',
            summary: "Se encontraron {$count} clientes activos en tu emprendimiento.",
            data: $data
        );
    }

    /**
     * Count customers for the specified tenant (read-only).
     */
    public function count(int $businessId): AiQueryResult
    {
        $total = Customer::where('business_id', $businessId)->where('is_active', true)->count();

        return new AiQueryResult(
            intent: 'count_customers',
            summary: "Actualmente tienes un total de {$total} clientes activos registrados.",
            data: ['total_customers' => $total]
        );
    }
}
