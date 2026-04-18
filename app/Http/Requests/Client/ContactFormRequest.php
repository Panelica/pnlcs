<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class ContactFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'department_id' => 'required|exists:ticket_departments,id',
            'subject' => 'required|string|max:255|min:3',
            'message' => 'required|string|min:10|max:10000',
        ];
    }
}
