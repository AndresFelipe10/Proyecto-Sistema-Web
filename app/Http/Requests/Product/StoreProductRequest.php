<?php

namespace App\Http\Requests\Product;

use App\Http\Requests\Concerns\NormalizesCopMonetaryFields;
use App\Models\Product;
use App\Services\Tenant\TenantManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    use NormalizesCopMonetaryFields;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Product::class);
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

        return [
            'name' => ['required', 'string', 'max:255'],
            'product_type' => ['nullable', 'string', Rule::in(['standard', 'raw_material', 'dish'])],
            'base_unit' => ['nullable', 'string', Rule::in(['unit', 'gram', 'milliliter'])],
            'sku' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('products', 'sku')->where('business_id', $tenantId),
            ],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('business_id', $tenantId),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'cost_price' => [$isRestaurant ? 'nullable' : 'required_without:purchase_price', 'numeric', 'min:0'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
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

        $sku = $this->input('sku');
        if (is_string($sku)) {
            $sku = trim($sku);
            if ($sku === '') {
                $sku = null;
            }
        }

        $productType = $this->input('product_type');
        if (empty($productType)) {
            $productType = $isRestaurant ? 'dish' : 'standard';
        }

        $rawCost = $this->input('cost_price') ?? $this->input('purchase_price');
        $normalizedCost = $this->normalizeCopMoney($rawCost);
        $normalizedSale = $this->normalizeCopMoney($this->input('sale_price'));

        $finalCost = ($isRestaurant && $productType === 'dish' && ($normalizedCost === null || $normalizedCost === ''))
            ? 0.00
            : ($normalizedCost !== null && $normalizedCost !== '' ? $normalizedCost : 0.00);

        $this->merge([
            'sku' => $sku,
            'is_active' => $this->boolean('is_active', true),
            'cost_price' => $finalCost,
            'purchase_price' => $finalCost,
            'sale_price' => $normalizedSale,
            'stock' => ($isRestaurant && $productType === 'dish' && ($this->input('stock') === null || $this->input('stock') === '')) ? '0' : ($this->input('stock') ?? '0'),
            'min_stock' => ($isRestaurant && $productType === 'dish' && ($this->input('min_stock') === null || $this->input('min_stock') === '')) ? '0' : ($this->input('min_stock') ?? '0'),
            'product_type' => $productType,
            'base_unit' => $this->input('base_unit') ?: 'unit',
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
            'sku.unique' => 'El código SKU ya está registrado en este emprendimiento.',
            'category_id.exists' => 'La categoría seleccionada no es válida.',
        ];
    }
}
