<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TicketPredefinedReply extends Model {
    protected $table = "ticket_predefined_replies";
    protected $fillable = ["category_id", "name", "reply"];
    public function category() { return $this->belongsTo(TicketPredefinedCategory::class, "category_id"); }
}
