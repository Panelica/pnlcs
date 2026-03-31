<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contact extends Model
{
    use HasFactory;

    protected $fillable = [
        "client_id", "first_name", "last_name", "email", "company_name",
        "address1", "address2", "city", "state", "postcode", "country",
        "phone_number", "is_sub_account", "password", "permissions",
        "general_emails", "product_emails", "domain_emails",
        "invoice_emails", "support_emails",
    ];

    protected function casts(): array
    {
        return [
            "permissions" => "array",
            "is_sub_account" => "boolean",
            "general_emails" => "boolean",
            "product_emails" => "boolean",
            "domain_emails" => "boolean",
            "invoice_emails" => "boolean",
            "support_emails" => "boolean",
            "password" => "hashed",
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
