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
            'payment_method' => ['nullable', Rule::in(['cash', 'transfer', 'card', 'other', 'mixed'])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                Rule::exists('products', 'id')->where('business_id', $businessId),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99999'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],

            // Validación de pagos mixtos
            'payments' => ['nullable', 'array', 'min:1', 'max:5'],
            'payments.*.method' => ['required_with:payments', Rule::in(\App\Enums\PaymentMethod::values())],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'gt:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:60'],
            'payments.*.cash_received' => ['nullable', 'numeric', 'min:0'],
            'payments.*.change_given' => ['nullable', 'numeric', 'min:0'],

            // Campos de Restaurante (Bloque R-D)
            'restaurant_order_id' => [
                'nullable',
                Rule::exists('restaurant_orders', 'id')->where('business_id', $businessId),
            ],
            'order_type' => ['nullable', Rule::in(['retail', 'table', 'delivery', 'takeout'])],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
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

        if ($this->has('restaurant_order_id') && ($this->input('restaurant_order_id') === '' || $this->input('restaurant_order_id') === 'null')) {
            $this->merge(['restaurant_order_id' => null]);
        }

        if ($this->has('delivery_fee') && ($this->input('delivery_fee') === '' || $this->input('delivery_fee') === null)) {
            $this->merge(['delivery_fee' => 0.00]);
        }

        // Sanitizar referencias en payments si existen
        if ($this->has('payments') && is_array($this->input('payments'))) {
            $cleanedPayments = [];
            foreach ($this->input('payments') as $p) {
                if (isset($p['reference'])) {
                    $p['reference'] = strip_tags(trim((string)$p['reference']));
                }
                $cleanedPayments[] = $p;
            }
            $this->merge(['payments' => $cleanedPayments]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $payments = $this->input('payments');

            if (is_array($payments) && count($payments) > 0) {
                $cashCount = 0;
                foreach ($payments as $index => $payment) {
                    $method = $payment['method'] ?? '';
                    $amount = (float) ($payment['amount'] ?? 0);

                    if ($method === 'cash') {
                        $cashCount++;
                        if (isset($payment['cash_received']) && $payment['cash_received'] !== '' && $payment['cash_received'] !== null) {
                            $cashReceived = (float) $payment['cash_received'];
                            if ($cashReceived < $amount) {
                                $validator->errors()->add("payments.{$index}.cash_received", 'El efectivo recibido debe ser mayor o igual al monto asignado en efectivo.');
                            }
                        }
                    }
                }

                if ($cashCount > 1) {
                    $validator->errors()->add('payments', 'Solo se permite una línea de pago con método Efectivo.');
                }
            } elseif (!$this->has('payment_method')) {
                $validator->errors()->add('payment_method', 'Debe especificar el método de pago o la lista de pagos.');
            }
        });
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
