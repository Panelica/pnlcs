<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class GatewaySettings extends Model {
    protected $table = "gateway_settings";
    protected $fillable = ["gateway", "setting", "value", "sort_order"];
}
