<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:clients,email',
            'company_name' => 'nullable|string|max:255',
            'address1' => 'nullable|string|max:255',
            'address2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'postcode' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:2',
            'phone_number' => 'nullable|string|max:30',
            'phone_prefix' => 'nullable|string|max:4',
            'status' => 'required|in:active,inactive,closed',
            'group_id' => 'nullable|exists:client_groups,id',
            'currency_id' => 'nullable|exists:currencies,id',
            'notes' => 'nullable|string|max:5000',
        ];
    }
}
