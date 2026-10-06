<?php

namespace App\Http\Requests\Restaurant;

use Illuminate\Foundation\Http\FormRequest;

class CancelRestaurantOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $this->user()?->can('cancel', $order) ?? false;
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
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'El motivo de anulación de la comanda es obligatorio.',
            'reason.min' => 'El motivo debe contener al menos 4 caracteres.',
            'reason.max' => 'El motivo no puede exceder los 500 caracteres.',
        ];
    }
}
