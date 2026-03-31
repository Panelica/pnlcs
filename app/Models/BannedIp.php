<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class BannedIp extends Model {
    protected $table = "banned_ips";
    protected $fillable = ["ip", "reason"];

}
