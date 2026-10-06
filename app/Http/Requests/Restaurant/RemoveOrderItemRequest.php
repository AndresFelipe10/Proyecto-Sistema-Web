<?php

namespace App\Http\Requests\Restaurant;

use Illuminate\Foundation\Http\FormRequest;

class RemoveOrderItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $this->user()?->can('deleteItem', $order) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:4', 'max:500'],
            'quantity_to_remove' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $item = $this->route('item');
            $qtyToRemove = $this->input('quantity_to_remove');

            if ($item && is_numeric($qtyToRemove)) {
                if ((int) $qtyToRemove > (int) $item->quantity) {
                    $validator->errors()->add(
                        'quantity_to_remove',
                        "La cantidad a retirar ({$qtyToRemove}) no puede ser mayor a la cantidad actual del plato ({$item->quantity})."
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'El motivo de eliminación del ítem es obligatorio.',
            'reason.min' => 'El motivo debe contener al menos 4 caracteres.',
            'reason.max' => 'El motivo no puede exceder los 500 caracteres.',
            'quantity_to_remove.required' => 'Debes especificar la cantidad a retirar.',
            'quantity_to_remove.integer' => 'La cantidad a retirar debe ser un número entero.',
            'quantity_to_remove.min' => 'La cantidad a retirar debe ser al menos 1.',
        ];
    }
}
