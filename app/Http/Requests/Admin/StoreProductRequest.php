<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'group_id' => 'required|exists:product_groups,id',
            'type' => 'required|in:hostingaccount,reselleraccount,server,other',
            'description' => 'nullable|string|max:10000',
            'hidden' => 'boolean',
            'retired' => 'boolean',
            'pay_type' => 'required|in:free,onetime,recurring',
            'auto_setup' => 'nullable|in:order,payment,manual',
            'server_type' => 'nullable|string|max:50',
            'server_group_id' => 'nullable|exists:server_groups,id',
            'tax' => 'boolean',
            'stock_control' => 'boolean',
            'stock_qty' => 'nullable|integer|min:0',
            'sort_order' => 'nullable|integer|min:0',
        ];
    }
}
