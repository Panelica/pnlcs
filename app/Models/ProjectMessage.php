<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class ProjectMessage extends Model {
    use HasFactory;

    protected $fillable = ["project_id","message","admin","client_id"];
    public function project() { return $this->belongsTo(Project::class); }
}
