<?php

namespace App\Http\Requests\Restaurant;

use App\Http\Requests\Concerns\NormalizesCopMonetaryFields;
use App\Models\RestaurantOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeliveryOrderRequest extends FormRequest
{
    use NormalizesCopMonetaryFields;

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
            'customer_name' => [
                'required',
                'string',
                'min:2',
                'max:80',
                'regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s.\'-]+$/u',
            ],
            'delivery_phone' => [
                Rule::requiredIf($this->input('order_type') === 'delivery'),
                'nullable',
                'string',
                'min:7',
                'max:15',
                'regex:/^\+?[0-9]{7,15}$/',
            ],
            'delivery_address' => [
                Rule::requiredIf($this->input('order_type') === 'delivery'),
                'nullable',
                'string',
                'min:5',
                'max:150',
                'regex:/^(?!.*(.)\1{5,})[a-zA-Z0-9áéíóúÁÉÍÓÚñÑüÜ\s#\-\.,\/°]+$/u',
            ],
            'delivery_notes' => ['nullable', 'string', 'max:255'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0', 'max:1000000', 'regex:/^\d+(\.\d{1,2})?$/'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['nullable', 'array'],
            'items.*.product_id' => [
                'required_with:items',
                'integer',
                Rule::exists('products', 'id')
                    ->where(fn ($query) => $query->where('business_id', $businessId)->where('is_active', true)),
            ],
            'items.*.quantity' => ['required_with:items', 'numeric', 'min:0.001', 'max:9999', 'regex:/^\d+(\.\d{1,3})?$/'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'order_type.in' => 'El tipo de pedido debe ser a domicilio o para llevar.',
            'customer_name.required' => 'El nombre del cliente es obligatorio para pedidos a domicilio o para llevar.',
            'customer_name.min' => 'El nombre del cliente debe tener al menos 2 caracteres.',
            'customer_name.max' => 'El nombre del cliente no puede superar los 80 caracteres.',
            'customer_name.regex' => 'El nombre del cliente solo debe contener letras, espacios, puntos o guiones.',
            'delivery_phone.required' => 'El teléfono de contacto es obligatorio para pedidos a domicilio.',
            'delivery_phone.min' => 'El teléfono debe tener entre 7 y 15 dígitos.',
            'delivery_phone.max' => 'El teléfono no puede superar los 15 caracteres.',
            'delivery_phone.regex' => 'El teléfono debe contener únicamente números (entre 7 y 15 dígitos) y opcionalmente el prefijo +.',
            'delivery_address.required' => 'La dirección de entrega es obligatoria para pedidos a domicilio.',
            'delivery_address.min' => 'La dirección de entrega debe tener al menos 5 caracteres.',
            'delivery_address.max' => 'La dirección de entrega no puede superar los 150 caracteres.',
            'delivery_address.regex' => 'La dirección de entrega contiene caracteres no válidos o repetición indebida de caracteres.',
            'delivery_fee.min' => 'El costo de envío no puede ser negativo.',
            'delivery_fee.max' => 'El costo de envío no puede superar $1.000.000.',
            'delivery_fee.numeric' => 'El costo de envío debe ser un valor numérico.',
            'items.*.product_id.exists' => 'Uno de los platos o productos seleccionados no existe o está inactivo.',
            'items.*.quantity.min' => 'La cantidad mínima por ítem debe ser mayor a 0.',
            'items.*.quantity.max' => 'La cantidad por ítem no puede superar 9.999 unidades.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('customer_name') && is_string($this->input('customer_name'))) {
            $this->merge(['customer_name' => trim($this->input('customer_name'))]);
        }

        if ($this->has('delivery_fee')) {
            $this->merge(['delivery_fee' => $this->normalizeCopMoney($this->input('delivery_fee'))]);
        }

        if ($this->input('order_type') === 'takeout') {
            if ($this->input('delivery_address') === '') {
                $this->merge(['delivery_address' => null]);
            }
            if ($this->input('delivery_phone') === '') {
                $this->merge(['delivery_phone' => null]);
            }
        }
    }
}
