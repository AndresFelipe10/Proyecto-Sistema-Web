<?php

namespace App\Http\Requests\Expense;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateExpenseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $expense = $this->route('expense');
        return $expense instanceof Expense && Gate::allows('update', $expense);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $businessId = (int) session('current_business_id');

        return [
            'supplier_id' => [
                'nullable',
                Rule::exists('suppliers', 'id')->where('business_id', $businessId),
            ],
            'invoice_number' => ['nullable', 'string', 'max:50'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'category' => ['required', 'string', Rule::in(ExpenseCategory::values())],
            'description' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'status' => ['required', Rule::in(['paid', 'pending'])],
            'paid_at' => [
                Rule::requiredIf(fn() => $this->input('status') === 'paid'),
                'nullable',
                'date',
            ],
            'payment_method' => [
                Rule::requiredIf(fn() => $this->input('status') === 'paid'),
                'nullable',
                'string',
                Rule::in(PaymentMethod::values()),
            ],
            'attachment' => [
                'nullable',
                'file',
                'max:3072',
                'mimes:jpg,jpeg,png,pdf',
            ],
            'remove_attachment' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $businessId = (int) session('current_business_id');
            $supplierId = $this->input('supplier_id');
            $invoiceNumber = trim((string) $this->input('invoice_number'));
            $expense = $this->route('expense');

            if (!empty($supplierId) && !empty($invoiceNumber) && $expense instanceof Expense) {
                $exists = Expense::withoutGlobalScopes()
                    ->where('business_id', $businessId)
                    ->where('supplier_id', $supplierId)
                    ->where('invoice_number', $invoiceNumber)
                    ->where('id', '!=', $expense->id)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add(
                        'invoice_number',
                        'Ya existe otra factura registrada con este número para el proveedor seleccionado.'
                    );
                }
            }

            if ($this->hasFile('attachment')) {
                $file = $this->file('attachment');
                $originalName = strtolower($file->getClientOriginalName());
                $mimeType = $file->getMimeType();

                $disallowedPatterns = '/\.(php[0-9]?|phtml|phar|exe|sh|bat|cmd|html|htm|svg|js|cgi|pl|py)(\.|$)/i';
                if (preg_match($disallowedPatterns, $originalName)) {
                    $validator->errors()->add('attachment', 'El archivo contiene extensiones no permitidas por seguridad.');
                }

                $allowedMimes = ['image/jpeg', 'image/png', 'application/pdf'];
                if (!in_array($mimeType, $allowedMimes)) {
                    $validator->errors()->add('attachment', 'El tipo MIME real del archivo no está permitido (solo JPG, PNG o PDF).');
                }
            }
        });
    }

    /**
     * Custom attribute names.
     */
    public function attributes(): array
    {
        return [
            'supplier_id' => 'proveedor',
            'invoice_number' => 'número de factura',
            'issue_date' => 'fecha de emisión',
            'due_date' => 'fecha de vencimiento',
            'category' => 'categoría',
            'description' => 'descripción',
            'amount' => 'monto',
            'status' => 'estado',
            'paid_at' => 'fecha de pago',
            'payment_method' => 'método de pago',
            'attachment' => 'comprobante adjunto',
        ];
    }
}
