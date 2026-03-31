<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TodoItem extends Model {
    protected $table = "todo_items";
    protected $fillable = ["title", "description", "status", "due_date", "admin"];

}
