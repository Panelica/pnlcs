<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ServiceAddon extends Model {
    use HasFactory;

    protected $fillable = ["order_id", "service_id", "addon_id", "client_id", "server_id", "qty", "amount", "billing_cycle", "next_due_date", "status", "notes"];
    protected function casts(): array { return ["next_due_date" => "date", "amount" => "decimal:2"]; }
    public function service() { return $this->belongsTo(Service::class); }
    public function client() { return $this->belongsTo(Client::class); }
}
