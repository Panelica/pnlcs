<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:50|unique:promotions,code|alpha_dash',
            'type' => 'required|in:Percentage,Fixed Amount,Price Override,Free Setup',
            'value' => 'required|numeric|min:0',
            'recurring' => 'boolean',
            'cycles' => 'nullable|integer|min:0',
            'start_date' => 'nullable|date',
            'expiration_date' => 'nullable|date|after_or_equal:start_date',
            'max_uses' => 'nullable|integer|min:0',
            'new_signups_only' => 'boolean',
            'existing_client' => 'boolean',
            'apply_once' => 'boolean',
            'lifetime_promo' => 'boolean',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
