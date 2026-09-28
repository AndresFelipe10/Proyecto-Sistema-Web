<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $this->user()->can('update', $customer);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $businessId = session('current_business_id');
        $customer = $this->route('customer');

        return [
            'name' => ['required', 'string', 'max:255'],
            'document' => [
                'required',
                'string',
                'regex:/^\d{5,15}(-\d)?$/',
                'not_in:222222222222',
                Rule::unique('customers', 'document')
                    ->where('business_id', $businessId)
                    ->ignore($customer ? $customer->id : null),
            ],
            'identification_number' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Custom message for validation errors.
     */
    public function messages(): array
    {
        return [
            'document.required' => 'El documento o NIT es obligatorio.',
            'document.regex' => 'El documento debe contener entre 5 y 15 dígitos numéricos (con guión y dígito opcional para NIT).',
            'document.not_in' => 'El documento 222222222222 está reservado para Consumidor Final de la DIAN.',
            'document.unique' => 'Ya existe un cliente registrado con este documento en este negocio.',
        ];
    }

    /**
     * Prepare data for validation.
     */
    protected function prepareForValidation(): void
    {
        $doc = $this->input('document') ?? $this->input('identification_number');

        if ($doc !== null) {
            $doc = trim((string) $doc);
            $this->merge([
                'document' => $doc,
                'identification_number' => $doc,
            ]);
        }

        $this->merge([
            'is_active' => $this->boolean('is_active', true),
        ]);
    }
}
