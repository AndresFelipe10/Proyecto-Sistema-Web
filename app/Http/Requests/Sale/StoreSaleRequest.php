<?php

namespace App\Http\Requests\Sale;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('create', Sale::class);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $businessId = session('current_business_id');

        return [
            'sale_date' => ['required', 'date'],
            'customer_id' => [
                'nullable',
                Rule::exists('customers', 'id')->where('business_id', $businessId),
            ],
            'discount_percentage' => ['required', 'numeric', 'between:0,100'],
            'payment_method' => ['required', Rule::in(['cash', 'transfer', 'card', 'other'])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                Rule::exists('products', 'id')->where('business_id', $businessId),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99999'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Prepare data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('customer_id') && ($this->input('customer_id') === '' || $this->input('customer_id') === 'null')) {
            $this->merge(['customer_id' => null]);
        }
    }

    /**
     * Get custom attribute names for validator errors.
     */
    public function attributes(): array
    {
        return [
            'sale_date' => 'fecha de venta',
            'customer_id' => 'cliente',
            'discount_percentage' => 'porcentaje de descuento',
            'payment_method' => 'método de pago',
            'notes' => 'notas',
            'items' => 'productos',
            'items.*.product_id' => 'producto',
            'items.*.quantity' => 'cantidad',
            'items.*.unit_price' => 'precio unitario',
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Debe agregar al menos un producto a la venta.',
            'items.min' => 'Debe agregar al menos un producto a la venta.',
            'items.*.product_id.exists' => 'El producto seleccionado no existe o no pertenece a este negocio.',
            'items.*.quantity.min' => 'La cantidad mínima por producto es 1.',
            'items.*.quantity.max' => 'La cantidad máxima por producto es 99999.',
        ];
    }
}
