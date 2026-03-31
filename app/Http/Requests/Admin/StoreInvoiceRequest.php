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
            'items.*.amount' => 'required|numeric|min:0',
            'items.*.taxed' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'At least one line item is required.',
            'items.*.description.required' => 'Each line item must have a description.',
            'items.*.amount.required' => 'Each line item must have an amount.',
        ];
    }
}
