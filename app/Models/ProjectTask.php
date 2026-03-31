<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ProjectTask extends Model {
    protected $fillable = ["project_id","task","notes","admin","completed","due_date","sort_order"];
    public function project() { return $this->belongsTo(Project::class); }
}
