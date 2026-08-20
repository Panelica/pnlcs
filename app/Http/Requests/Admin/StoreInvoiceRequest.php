<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => 'required|exists:clients,id',
            'date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:date',
            'payment_method' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:5000',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:500',
            'items.*.qty' => 'nullable|integer|min:1|max:999999',
            'items.*.amount' => 'required|numeric|min:0',
            'items.*.taxed' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => __('messages.validation.at_least_one_line_item'),
            'items.*.description.required' => __('messages.validation.line_item_description_required'),
            'items.*.amount.required' => __('messages.validation.line_item_amount_required'),
        ];
    }
}
