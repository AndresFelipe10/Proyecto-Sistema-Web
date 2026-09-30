<?php

namespace App\Http\Requests\Restaurant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddOrderItemsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $this->user()->can('update', $order);
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')
                    ->where(fn ($query) => $query->where('business_id', $businessId)->where('is_active', true)),
            ],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001', 'regex:/^\d+(\.\d{1,3})?$/'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Debes agregar al menos un plato o producto a la comanda.',
            'items.min' => 'Debes agregar al menos un plato o producto a la comanda.',
            'items.*.product_id.exists' => 'Uno de los productos o platos seleccionados no existe o está inactivo.',
            'items.*.quantity.min' => 'La cantidad mínima por ítem debe ser mayor a 0.',
        ];
    }
}
