<?php

namespace App\Http\Requests\Sale;

use App\Http\Requests\Concerns\NormalizesCopMonetaryFields;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
{
    use NormalizesCopMonetaryFields;

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
            'items.*.quantity' => ['required', 'numeric', 'min:0.001', 'max:99999'],
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
            'service_fee' => ['nullable', 'numeric', 'min:0'],
            'tax_inc' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Sanitiza y convierte strings con formato de moneda a floats canónicos.
     * Soporta "$650,000.00", "650000,00", "650.000", "1.250.000,50", etc.
     */
    protected function sanitizeCurrency(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = $this->normalizeCopMoney($value);

        return is_numeric($normalized) ? (float) $normalized : null;
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

        if ($this->has('delivery_fee')) {
            $rawFee = $this->input('delivery_fee');
            $this->merge(['delivery_fee' => ($rawFee === '' || $rawFee === null) ? 0.00 : ($this->sanitizeCurrency($rawFee) ?? 0.00)]);
        }

        if ($this->has('service_fee')) {
            $rawService = $this->input('service_fee');
            $this->merge(['service_fee' => ($rawService === '' || $rawService === null) ? 0.00 : ($this->sanitizeCurrency($rawService) ?? 0.00)]);
        }

        if ($this->has('tax_inc')) {
            $rawInc = $this->input('tax_inc');
            $this->merge(['tax_inc' => ($rawInc === '' || $rawInc === null) ? 0.00 : ($this->sanitizeCurrency($rawInc) ?? 0.00)]);
        }

        if ($this->has('payment_method')) {
            $rawMethod = strtolower(trim((string) $this->input('payment_method')));
            if (in_array($rawMethod, ['nequi', 'daviplata'], true)) {
                $this->merge(['payment_method' => 'transfer']);
            }
        }

        // Sanitizar referencias y montos en payments si existen
        if ($this->has('payments') && is_array($this->input('payments'))) {
            $cleanedPayments = [];
            foreach ($this->input('payments') as $p) {
                if (isset($p['method'])) {
                    $rawMethod = strtolower(trim((string) $p['method']));
                    if (in_array($rawMethod, ['nequi', 'daviplata'], true)) {
                        $p['method'] = 'transfer';
                        if (empty($p['reference'])) {
                            $p['reference'] = ucfirst($rawMethod);
                        }
                    }
                }
                if (isset($p['amount'])) {
                    $p['amount'] = $this->sanitizeCurrency($p['amount']);
                }
                if (isset($p['cash_received']) && $p['cash_received'] !== '' && $p['cash_received'] !== null) {
                    $p['cash_received'] = $this->sanitizeCurrency($p['cash_received']);
                }
                if (isset($p['change_given']) && $p['change_given'] !== '' && $p['change_given'] !== null) {
                    $p['change_given'] = $this->sanitizeCurrency($p['change_given']);
                }
                if (isset($p['reference'])) {
                    $ref = strip_tags(trim((string) $p['reference']));
                    $p['reference'] = mb_substr($ref, 0, 60);
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
                            // Tolerancia decimal (0.01) para blindar contra imprecisiones de coma flotante
                            if (($amount - $cashReceived) > 0.01) {
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
            'delivery_fee' => 'costo de envío o domicilio',
            'service_fee' => 'servicio o propina voluntaria',
            'tax_inc' => 'impuesto nacional al consumo',
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
            'delivery_fee.min' => 'El costo de envío no puede ser negativo.',
            'service_fee.min' => 'El valor del servicio o propina no puede ser negativo.',
            'tax_inc.min' => 'El valor del Impuesto al Consumo (INC) no puede ser negativo.',
        ];
    }
}
