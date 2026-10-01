<?php

namespace App\Http\Requests\Restaurant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRestaurantTableRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\RestaurantTable::class);
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
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('restaurant_tables', 'name')->where(fn ($query) => $query->where('business_id', $businessId)),
            ],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:200'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Ya existe una mesa con este nombre en tu restaurante.',
            'capacity.min' => 'La capacidad mínima de la mesa debe ser de al menos 1 persona.',
        ];
    }
}
