<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TicketPredefinedCategory extends Model {
    protected $table = "ticket_predefined_categories";
    protected $fillable = ["parent_id", "name"];
    public function replies() { return $this->hasMany(TicketPredefinedReply::class, "category_id"); }
}
