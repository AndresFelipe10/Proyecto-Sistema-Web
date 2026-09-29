<?php

namespace App\Http\Requests\Customer;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Customer::class);
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
                'max:255',
                'regex:/[a-zA-ZáéíóúÁÉÍÓÚñÑ]/',
            ],
            'document' => [
                'required',
                'string',
                'regex:/^\d{5,15}(-\d)?$/',
                'not_in:222222222222',
                Rule::unique('customers', 'document')->where('business_id', $businessId),
            ],
            'identification_number' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[\d\s+\-()]{7,20}$/'],
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
            'name.required' => 'El nombre o razón social es obligatorio.',
            'name.regex' => 'El nombre o razón social debe contener letras y no solo números.',
            'document.required' => 'El documento o NIT es obligatorio.',
            'document.regex' => 'El documento debe contener entre 5 y 15 dígitos numéricos (con guión y dígito opcional para NIT).',
            'document.not_in' => 'El documento 222222222222 está reservado para Consumidor Final de la DIAN.',
            'document.unique' => 'Ya existe un cliente registrado con este documento en este negocio.',
            'phone.regex' => 'El teléfono solo debe contener números, espacios o los símbolos + y - (entre 7 y 20 caracteres).',
            'email.email' => 'El campo correo electrónico debe ser una dirección de correo válida.',
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

        $phone = $this->input('phone');
        if ($phone !== null && trim((string)$phone) === '') {
            $phone = null;
        }

        $email = $this->input('email');
        if ($email !== null && trim((string)$email) === '') {
            $email = null;
        }

        $this->merge([
            'phone' => $phone,
            'email' => $email,
            'is_active' => $this->boolean('is_active', true),
        ]);
    }
}
