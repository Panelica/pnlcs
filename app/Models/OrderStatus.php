<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class OrderStatus extends Model {
    protected $table = "order_statuses";
    protected $fillable = ["title", "color", "show_pending", "show_active", "show_cancelled", "sort_order"];
}
