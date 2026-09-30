<?php

namespace App\Http\Requests\Restaurant;

use App\Models\RestaurantOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRestaurantOrderRequest extends FormRequest
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
            'table_id' => [
                'required',
                'integer',
                Rule::exists('restaurant_tables', 'id')
                    ->where(fn ($query) => $query->where('business_id', $businessId)->where('is_active', true)),
            ],
            'customer_name' => ['nullable', 'string', 'max:150'],
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
            'table_id.required' => 'Debes seleccionar una mesa válida para abrir la comanda.',
            'table_id.exists' => 'La mesa seleccionada no existe o se encuentra inactiva.',
            'items.*.product_id.exists' => 'Uno de los productos o platos seleccionados no existe o está inactivo.',
            'items.*.quantity.min' => 'La cantidad mínima por ítem debe ser mayor a 0.',
        ];
    }
}
