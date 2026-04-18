<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class SubmitTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'department_id' => 'required|exists:ticket_departments,id',
            'title' => 'required|string|max:255|min:5',
            'message' => 'required|string|min:10|max:10000',
            'priority' => 'required|in:Low,Medium,High',
            'service_id' => 'nullable|exists:services,id',
        ];
    }
}
