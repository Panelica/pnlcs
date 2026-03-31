<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class BannedEmail extends Model {
    protected $table = "banned_emails";
    protected $fillable = ["domain", "reason"];

}
