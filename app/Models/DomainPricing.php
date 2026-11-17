<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DomainPricing extends Model {
    protected $table = "domain_pricing";
    protected $fillable = [
        "extension", "category", "is_popular", "register_price", "transfer_price", "renew_price", "restore_price", "privacy_price",
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
            "is_popular"      => "boolean",
            "register_price"  => "float",
            "transfer_price"  => "float",
            "renew_price"     => "float",
            "restore_price"   => "float",
            "privacy_price"   => "float",
        ];
    }

    /**
     * Whether a transfer of this domain needs an EPP/auth code. Some
     * registries move a name without one (.uk uses a registrar tag instead),
     * set per extension with the epp_code flag. An unknown extension asks for
     * the code, as every transfer did before.
     */
    public static function transferNeedsAuthCode(string $domain): bool
    {
        $extension = '.'.implode('.', array_slice(explode('.', strtolower(trim($domain))), 1));
        $flag = static::where('extension', $extension)->value('epp_code');

        return $flag === null ? true : (bool) $flag;
    }
}
