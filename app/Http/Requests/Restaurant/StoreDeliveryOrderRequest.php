<?php

namespace App\Http\Requests\Restaurant;

use App\Models\RestaurantOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeliveryOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', RestaurantOrder::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $businessId = session('current_business_id');

        return [
            'order_type' => ['required', Rule::in(['delivery', 'takeout'])],
            'customer_id' => [
                'nullable',
                'integer',
                Rule::exists('customers', 'id')->where(fn ($query) => $query->where('business_id', $businessId)),
            ],
            'customer_name' => ['required', 'string', 'max:150'],
            'delivery_phone' => [
                Rule::requiredIf($this->input('order_type') === 'delivery'),
                'nullable',
                'string',
                'max:30',
            ],
            'delivery_address' => [
                Rule::requiredIf($this->input('order_type') === 'delivery'),
                'nullable',
                'string',
                'max:255',
            ],
            'delivery_notes' => ['nullable', 'string', 'max:500'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,2})?$/'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['nullable', 'array'],
            'items.*.product_id' => [
                'required_with:items',
                'integer',
                Rule::exists('products', 'id')
                    ->where(fn ($query) => $query->where('business_id', $businessId)->where('is_active', true)),
            ],
            'items.*.quantity' => ['required_with:items', 'numeric', 'min:0.001', 'regex:/^\d+(\.\d{1,3})?$/'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'order_type.in' => 'El tipo de pedido debe ser a domicilio o para llevar.',
            'customer_name.required' => 'El nombre del cliente es obligatorio para pedidos a domicilio o para llevar.',
            'delivery_address.required' => 'La dirección de entrega es obligatoria para pedidos a domicilio.',
            'delivery_phone.required' => 'El teléfono de contacto es obligatorio para pedidos a domicilio.',
            'delivery_fee.min' => 'El costo de envío no puede ser negativo.',
            'delivery_fee.numeric' => 'El costo de envío debe ser un valor numérico.',
            'items.*.product_id.exists' => 'Uno de los platos o productos seleccionados no existe o está inactivo.',
            'items.*.quantity.min' => 'La cantidad mínima por ítem debe ser mayor a 0.',
        ];
    }
}
