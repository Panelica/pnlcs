<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ProjectMessage extends Model {
    protected $fillable = ["project_id","message","admin","client_id"];
    public function project() { return $this->belongsTo(Project::class); }
}
