<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreServerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'hostname' => 'required|string|max:255',
            'ip_address' => 'nullable|ip',
            'port' => 'nullable|integer|min:1|max:65535',
            'type' => 'required|string|max:50',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'access_hash' => 'nullable|string',
            'max_accounts' => 'nullable|integer|min:0',
            'nameserver1' => 'nullable|string|max:255',
            'nameserver2' => 'nullable|string|max:255',
            'nameserver3' => 'nullable|string|max:255',
            'nameserver4' => 'nullable|string|max:255',
            'nameserver5' => 'nullable|string|max:255',
            'active' => 'boolean',
        ];
    }
}
