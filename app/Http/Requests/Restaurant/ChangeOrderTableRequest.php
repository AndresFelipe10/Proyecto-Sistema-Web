<?php

namespace App\Http\Requests\Restaurant;

use App\Models\RestaurantOrder;
use App\Models\RestaurantTable;
use Illuminate\Foundation\Http\FormRequest;

class ChangeOrderTableRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Allowed for any authenticated user belonging to the order's tenant (waiters, cashiers, admins).
     */
    public function authorize(): bool
    {
        $order = $this->route('order');

        if (! $order instanceof RestaurantOrder) {
            $order = RestaurantOrder::find($order);
        }

        if (! $order) {
            return false;
        }

        $user = $this->user();
        if (! $user) {
            return false;
        }

        $businessId = (int) session('current_business_id');

        if ((int) $order->business_id !== $businessId) {
            return false;
        }

        return $user->isCurrentAdmin() || $user->isCurrentEmployee();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'table_id' => ['required', 'integer', 'exists:restaurant_tables,id'],
        ];
    }

    /**
     * Custom validation rules to enforce tenant isolation and table availability.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $tableId = $this->input('table_id');
            if (! $tableId) {
                return;
            }

            $businessId = (int) session('current_business_id');

            // Consultar mesa sin scope para verificar tenant estricto
            $targetTable = RestaurantTable::withoutGlobalScopes()->find($tableId);

            if (! $targetTable || (int) $targetTable->business_id !== $businessId) {
                $validator->errors()->add('table_id', 'La mesa seleccionada no pertenece al establecimiento activo.');
                return;
            }

            if ($targetTable->status !== 'available') {
                $validator->errors()->add('table_id', 'La mesa seleccionada ya se encuentra ocupada.');
                return;
            }

            $order = $this->route('order');
            if (! $order instanceof RestaurantOrder) {
                $order = RestaurantOrder::find($order);
            }

            if ($order && (int) $order->table_id === (int) $targetTable->id) {
                $validator->errors()->add('table_id', 'La comanda ya se encuentra asignada a esta mesa.');
            }
        });
    }

    /**
     * Custom validation error messages.
     */
    public function messages(): array
    {
        return [
            'table_id.required' => 'Debes seleccionar una mesa de destino.',
            'table_id.integer' => 'El identificador de mesa no es válido.',
            'table_id.exists' => 'La mesa seleccionada no existe en el sistema.',
        ];
    }
}
