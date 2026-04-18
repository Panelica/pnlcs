<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'email' => 'nullable|email|max:255',
            'clients_only' => 'boolean',
            'hidden' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
            'feedback_request' => 'boolean',
        ];
    }
}
