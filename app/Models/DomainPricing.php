<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DomainPricing extends Model {
    protected $table = "domain_pricing";
    protected $fillable = [
        "extension", "register_price", "transfer_price", "renew_price",
        "dns_management", "email_forwarding", "id_protection", "epp_code",
        "auto_registrar", "grace_period", "redemption_grace_period",
        "min_years", "max_years", "sort_order", "enabled"
    ];
    protected function casts(): array {
        return [
            "dns_management"  => "boolean",
            "email_forwarding" => "boolean",
            "id_protection"   => "boolean",
            "epp_code"        => "boolean",
            "enabled"         => "boolean",
            "register_price"  => "float",
            "transfer_price"  => "float",
            "renew_price"     => "float",
        ];
    }
}
