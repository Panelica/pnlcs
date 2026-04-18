<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BannedIp extends Model {
    use HasFactory;

    protected $table = "banned_ips";
    protected $fillable = ["ip", "reason"];

}
