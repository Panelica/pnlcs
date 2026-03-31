<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class GatewayLog extends Model {
    protected $table = "gateway_logs";
    protected $fillable = ["gateway", "date", "data", "result"];

}
