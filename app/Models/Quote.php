<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Quote extends Model {
    use HasFactory;

    protected $table = "quotes";
    protected $fillable = ["client_id", "subject", "date", "valid_until", "subtotal", "tax", "total", "status", "notes", "customer_notes", "proposal"];

    public function client() { return $this->belongsTo(Client::class); }
    public function items() { return $this->hasMany(QuoteItem::class); }
}
