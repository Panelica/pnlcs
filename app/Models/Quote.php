<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Quote extends Model {
    use HasFactory;

    protected $table = "quotes";
    protected $fillable = ["client_id", "subject", "date", "valid_until", "subtotal", "tax", "total", "status", "notes", "customer_notes", "proposal"];

    // Both are DATE columns; without a cast Eloquent hands back a raw string, and
    // the quotes list calls ->format() straight on it (a null-safe call does not
    // guard a string), throwing "member function format() on string".
    protected function casts(): array
    {
        return ['date' => 'date', 'valid_until' => 'date'];
    }

    public function client() { return $this->belongsTo(Client::class); }
    public function items() { return $this->hasMany(QuoteItem::class); }
}
