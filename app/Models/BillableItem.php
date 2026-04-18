<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BillableItem extends Model {
    use HasFactory;

    protected $table = "billable_items";
    protected $fillable = ["client_id", "description", "amount", "recur", "recur_cycle", "recur_for", "due_date", "invoice_id"];

    public function client() { return $this->belongsTo(Client::class); }
}
