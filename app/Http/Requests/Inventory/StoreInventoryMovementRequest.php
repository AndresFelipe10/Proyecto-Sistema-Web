<?php

namespace App\Http\Requests\Inventory;

use App\Models\InventoryMovement;
use App\Services\Tenant\TenantManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryMovementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', InventoryMovement::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = app(TenantManager::class)->id();
        $isAdjustment = $this->input('type') === InventoryMovement::TYPE_ADJUSTMENT;

        return [
            'product_id' => [
                'required',
                Rule::exists('products', 'id')->where('business_id', $tenantId),
            ],
            'type' => [
                'required',
                Rule::in([
                    InventoryMovement::TYPE_ENTRY,
                    InventoryMovement::TYPE_EXIT,
                    InventoryMovement::TYPE_ADJUSTMENT,
                ]),
            ],
            'quantity' => [
                'required',
                'numeric',
                $isAdjustment ? 'min:0' : 'gt:0',
                'regex:/^\d+(\.\d{1,3})?$/',
            ],
            'reason' => [
                'required',
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * Custom messages for validation errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_id.exists' => 'El producto seleccionado no pertenece a este emprendimiento.',
            'type.in' => 'El tipo de movimiento debe ser entrada, salida o ajuste.',
            'quantity.min' => 'La cantidad no puede ser negativa.',
            'quantity.gt' => 'La cantidad debe ser mayor a cero para entradas y salidas.',
            'reason.required' => 'Debes indicar el motivo o justificación del movimiento.',
        ];
    }
}
