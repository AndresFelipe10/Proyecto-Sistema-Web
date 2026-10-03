<?php

namespace App\Http\Requests\Product;

use App\Services\Tenant\TenantManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $this->user()->can('update', $product);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenant = app(TenantManager::class)->get();
        $tenantId = $tenant?->id ?? app(TenantManager::class)->id();
        $isRestaurant = $tenant?->isRestaurant();
        $product = $this->route('product');

        return [
            'name' => ['required', 'string', 'max:255'],
            'product_type' => ['nullable', 'string', Rule::in(['standard', 'raw_material', 'dish'])],
            'base_unit' => ['nullable', 'string', Rule::in(['unit', 'gram', 'milliliter'])],
            'sku' => [
                $isRestaurant ? 'nullable' : 'required',
                'string',
                'max:50',
                Rule::unique('products', 'sku')
                    ->where('business_id', $tenantId)
                    ->ignore($product->id),
            ],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('business_id', $tenantId),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'cost_price' => [$isRestaurant ? 'nullable' : 'required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'stock' => [$isRestaurant ? 'nullable' : 'required', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,3})?$/'],
            'min_stock' => [$isRestaurant ? 'nullable' : 'required', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,3})?$/'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $tenant = app(TenantManager::class)->get();
        $isRestaurant = $tenant?->isRestaurant();
        $product = $this->route('product');

        $sku = $this->input('sku');
        if (is_string($sku)) {
            $sku = trim($sku);
        }
        if (empty($sku)) {
            $sku = $product?->sku;
        }

        $this->merge([
            'sku' => $sku,
            'is_active' => $this->boolean('is_active', true),
            'cost_price' => $isRestaurant && ($this->input('cost_price') === null || $this->input('cost_price') === '') ? ($product?->cost_price ?? '0.00') : $this->input('cost_price'),
            'stock' => $isRestaurant && ($this->input('stock') === null || $this->input('stock') === '') ? ($product?->stock ?? '0') : $this->input('stock'),
            'min_stock' => $isRestaurant && ($this->input('min_stock') === null || $this->input('min_stock') === '') ? ($product?->min_stock ?? '0') : $this->input('min_stock'),
            'product_type' => $this->input('product_type') ?: ($product?->product_type ?? 'standard'),
            'base_unit' => $this->input('base_unit') ?: ($product?->base_unit ?? 'unit'),
        ]);
    }

    /**
     * Custom messages for validation errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sku.unique' => 'El código SKU ya está en uso por otro producto de este emprendimiento.',
            'category_id.exists' => 'La categoría seleccionada no es válida.',
        ];
    }
}
