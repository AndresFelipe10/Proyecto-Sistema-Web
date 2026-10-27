<?php

namespace App\Http\Requests\Restaurant;

use App\Http\Requests\Concerns\NormalizesCopMonetaryFields;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use Illuminate\Foundation\Http\FormRequest;

class SplitOrderPaymentRequest extends FormRequest
{
    use NormalizesCopMonetaryFields;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $order = $this->route('order');

        if (! $order instanceof RestaurantOrder) {
            $order = RestaurantOrder::find($order);
        }

        if (! $order) {
            return false;
        }

        $user = $this->user();
        if (! $user) {
            return false;
        }

        $businessId = (int) (session('current_business_id') ?? $user->business_id);

        if ((int) $order->business_id !== $businessId) {
            return false;
        }

        return $user->isCurrentAdmin() || $user->isCurrentEmployee();
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // 1. Limpieza de efectivo recibido y monto si vienen en formato moneda COP
        if ($this->has('cash_received')) {
            $this->merge(['cash_received' => $this->normalizeCopMoney($this->input('cash_received'))]);
        }
        if ($this->has('amount')) {
            $this->merge(['amount' => $this->normalizeCopMoney($this->input('amount'))]);
        }

        // 2. Normalizar billeteras electrónicas hacia 'transfer' en payment_method y reference legacy
        if ($this->filled('payment_method')) {
            $rawMethod = strtolower($this->input('payment_method'));
            if (in_array($rawMethod, ['nequi', 'daviplata'], true)) {
                $ref = $this->input('reference');
                if (empty($ref)) {
                    $this->merge(['reference' => ucfirst($rawMethod)]);
                }
                $this->merge(['payment_method' => 'transfer']);
            }
        }

        // 3. Compatibilidad hacia atrás: si no viene el arreglo 'payments', construirlo desde payment_method
        if (! $this->has('payments') && $this->filled('payment_method')) {
            $method = $this->input('payment_method');
            $received = $this->input('cash_received');
            $ref = $this->input('reference');
            $amt = $this->input('amount');

            // Si no se especificó un monto explícito en la llamada de método único, calcular subtotal de ítems
            // filtrando autoritativamente por orden de la ruta, tenant activo, estado pendiente y no cancelado
            if ($amt === null && $this->has('items') && is_array($this->input('items'))) {
                $order = $this->route('order');
                $orderId = $order instanceof RestaurantOrder ? $order->id : (is_numeric($order) ? (int) $order : null);
                $businessId = (int) (session('current_business_id') ?? $this->user()?->business_id);

                $query = RestaurantOrderItem::whereIn('id', $this->input('items'))
                    ->where('business_id', $businessId)
                    ->where('payment_status', 'pending')
                    ->where('status', '!=', 'cancelled');

                if ($orderId) {
                    $query->where('order_id', $orderId);
                }

                $itemsSubtotal = (float) $query->sum('subtotal');
                $amt = $itemsSubtotal > 0 ? $itemsSubtotal : 0.01;
            }

            $this->merge([
                'payments' => [
                    [
                        'method' => $method,
                        'amount' => $amt ?? 0.01,
                        'received' => $received,
                        'cash_received' => $received,
                        'reference' => $ref,
                    ]
                ]
            ]);
        }

        // 4. Normalizar arreglo 'payments' si fue provisto directamente
        if ($this->has('payments') && is_array($this->input('payments'))) {
            $payments = $this->input('payments');
            foreach ($payments as &$p) {
                if (isset($p['amount'])) {
                    $p['amount'] = $this->normalizeCopMoney($p['amount']);
                }
                if (isset($p['received'])) {
                    $p['received'] = $this->normalizeCopMoney($p['received']);
                }
                if (isset($p['cash_received'])) {
                    $p['cash_received'] = $this->normalizeCopMoney($p['cash_received']);
                }
                if (isset($p['method'])) {
                    $rawMethod = strtolower($p['method']);
                    if (in_array($rawMethod, ['nequi', 'daviplata'], true)) {
                        $p['method'] = 'transfer';
                        if (empty($p['reference'])) {
                            $p['reference'] = ucfirst($rawMethod);
                        }
                    }
                }
            }
            unset($p);
            $this->merge(['payments' => $payments]);
        }

        // 5. Normalizar switches booleanos
        if ($this->has('include_tip')) {
            $this->merge(['include_tip' => filter_var($this->input('include_tip'), FILTER_VALIDATE_BOOLEAN)]);
        }
        if ($this->has('include_tax')) {
            $this->merge(['include_tax' => filter_var($this->input('include_tax'), FILTER_VALIDATE_BOOLEAN)]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'integer', 'exists:restaurant_order_items,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'include_tip' => ['nullable', 'boolean'],
            'include_tax' => ['nullable', 'boolean'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'string', 'in:cash,card,transfer,nequi,daviplata,other'],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'payments.*.received' => ['nullable', 'numeric', 'min:0'],
            'payments.*.cash_received' => ['nullable', 'numeric', 'min:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:500'],
            // Compatibilidad legacy
            'payment_method' => ['nullable', 'string', 'in:cash,card,transfer,nequi,daviplata,other'],
            'cash_received' => ['nullable', 'numeric', 'min:0'],
            'reference' => ['nullable', 'string', 'max:60'],
        ];
    }

    /**
     * Custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Debe seleccionar al menos un plato para liquidar.',
            'items.array' => 'El formato de selección de platos no es válido.',
            'items.min' => 'Debe seleccionar al menos un plato para liquidar.',
            'items.*.exists' => 'Uno de los platos seleccionados no existe en el sistema.',
            'customer_id.exists' => 'El cliente seleccionado no es válido.',
            'discount_percentage.numeric' => 'El porcentaje de descuento debe ser un valor numérico.',
            'discount_percentage.min' => 'El descuento no puede ser inferior a 0%.',
            'discount_percentage.max' => 'El descuento no puede superar el 100%.',
            'payments.required' => 'Debe registrar al menos un método de pago.',
            'payments.min' => 'Debe registrar al menos un método de pago.',
            'payments.*.method.required' => 'Debe indicar el medio de pago.',
            'payments.*.method.in' => 'El medio de pago no es válido.',
            'payments.*.amount.required' => 'Debe especificar el monto a abonar en cada método de pago.',
            'payments.*.amount.min' => 'El monto a abonar debe ser mayor a cero.',
            'payments.*.received.min' => 'El efectivo recibido no puede ser negativo.',
            'payments.*.cash_received.min' => 'El efectivo recibido no puede ser negativo.',
            'payments.*.reference.max' => 'La referencia no puede exceder los 100 caracteres.',
            'notes.max' => 'Las notas u observaciones no pueden superar los 500 caracteres.',
        ];
    }

    /**
     * Configure additional validation checks for multi-tenancy and order item ownership.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $order = $this->route('order');
            if (! $order instanceof RestaurantOrder) {
                $order = RestaurantOrder::find($order);
            }

            if (! $order) {
                return;
            }

            $businessId = (int) (session('current_business_id') ?? $this->user()?->business_id);
            $itemIds = $this->input('items', []);

            // Validación de pertenencia tenant, orden y estado de los ítems
            if (is_array($itemIds) && ! empty($itemIds)) {
                $items = RestaurantOrderItem::where('order_id', $order->id)
                    ->where('business_id', $businessId)
                    ->whereIn('id', $itemIds)
                    ->where('payment_status', 'pending')
                    ->where('status', '!=', 'cancelled')
                    ->get();

                if ($items->count() !== count(array_unique($itemIds))) {
                    $validator->errors()->add('items', 'Uno o más platos seleccionados no pertenecen a esta comanda, ya fueron pagados o están cancelados.');
                }
            }

            // Validación multi-tenant de cliente si se suministró
            $customerId = $this->input('customer_id');
            if ($customerId) {
                $customerExists = \App\Models\Customer::where('business_id', $businessId)
                    ->where('id', $customerId)
                    ->exists();

                if (! $customerExists) {
                    $validator->errors()->add('customer_id', 'El cliente seleccionado no pertenece al establecimiento activo.');
                }
            }
        });
    }
}
