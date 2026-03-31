<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Quote extends Model {
    protected $table = "quotes";
    protected $fillable = ["client_id", "subject", "date", "valid_until", "subtotal", "tax", "total", "status", "notes", "customer_notes", "proposal"];

    public function client() { return $this->belongsTo(Client::class); }
    public function items() { return $this->hasMany(QuoteItem::class); }
}
