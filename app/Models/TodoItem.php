<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TodoItem extends Model {
    use HasFactory;

    protected $table = "todo_items";
    protected $fillable = ["title", "description", "status", "due_date", "admin"];
    protected $casts = ["due_date" => "date"];

}
