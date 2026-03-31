<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BannedEmail extends Model {
    use HasFactory;

    protected $table = "banned_emails";
    protected $fillable = ["domain", "reason"];

}
