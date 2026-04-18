<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class ProjectTask extends Model {
    use HasFactory;

    protected $fillable = ["project_id","task","notes","admin","completed","due_date","sort_order"];
    public function project() { return $this->belongsTo(Project::class); }
}
