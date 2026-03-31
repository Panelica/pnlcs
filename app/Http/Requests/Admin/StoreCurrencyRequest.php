<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|size:3|unique:currencies,code|alpha',
            'prefix' => 'nullable|string|max:10',
            'suffix' => 'nullable|string|max:10',
            'format' => 'nullable|integer|in:1,2,3,4',
            'rate' => 'required|numeric|min:0.00001',
        ];
    }
}
