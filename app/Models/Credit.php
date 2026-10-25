<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Credit extends Model {
    use HasFactory;

    protected $fillable = ["client_id", "admin_id", "date", "description", "amount"];
    protected function casts(): array { return ["date" => "date", "amount" => "decimal:2"]; }
    public function client() { return $this->belongsTo(Client::class); }
    /** The member of staff who added or took the credit by hand (ClientCreditController); null for the system's own lines. */
    public function admin() { return $this->belongsTo(Admin::class); }
}
