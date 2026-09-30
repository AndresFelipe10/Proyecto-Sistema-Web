<?php

namespace App\Http\Requests\Recipe;

use App\Services\Tenant\TenantManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('recipe'));
    }

    public function rules(): array
    {
        $tenantId = app(TenantManager::class)->id();
        $recipe = $this->route('recipe');

        return [
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where('business_id', $tenantId)->where('product_type', 'dish'),
                Rule::unique('recipes', 'product_id')->where('business_id', $tenantId)->ignore($recipe->id),
            ],
            'name' => ['required', 'string', 'max:150'],
            'is_active' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ingredient_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('products', 'id')->where('business_id', $tenantId)->where('product_type', 'raw_material'),
            ],
            'items.*.quantity_per_portion' => [
                'required',
                'numeric',
                'min:0.001',
                'regex:/^\d+(\.\d{1,3})?$/',
            ],
            'items.*.unit' => ['required', 'string', 'max:20'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
        ]);
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Debe seleccionar el plato para la receta.',
            'product_id.unique' => 'Este plato ya cuenta con otra receta registrada.',
            'product_id.exists' => 'El plato seleccionado es inválido o no pertenece a este negocio.',
            'name.required' => 'El nombre de la receta es obligatorio.',
            'items.required' => 'La receta debe contener al menos un ingrediente.',
            'items.min' => 'La receta debe contener al menos un ingrediente.',
            'items.*.ingredient_id.required' => 'El ingrediente es obligatorio.',
            'items.*.ingredient_id.distinct' => 'No puede repetir el mismo ingrediente en la receta.',
            'items.*.ingredient_id.exists' => 'El ingrediente seleccionado no es un insumo válido de este negocio.',
            'items.*.quantity_per_portion.required' => 'La cantidad por porción es obligatoria.',
            'items.*.quantity_per_portion.min' => 'La cantidad por porción debe ser mayor a 0.',
            'items.*.quantity_per_portion.regex' => 'La cantidad por porción admite máximo 3 decimales.',
        ];
    }
}
