<?php

namespace App\Http\Requests\Expense;

use App\Enums\PaymentMethod;
use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PayExpenseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $expense = $this->route('expense');
        return $expense instanceof Expense && Gate::allows('pay', $expense);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'paid_at' => ['required', 'date'],
            'payment_method' => ['required', 'string', Rule::in(PaymentMethod::values())],
        ];
    }

    /**
     * Custom attribute names.
     */
    public function attributes(): array
    {
        return [
            'paid_at' => 'fecha de pago',
            'payment_method' => 'método de pago',
        ];
    }
}
