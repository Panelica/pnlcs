<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class Project extends Model {
    use HasFactory;

    protected $fillable = ["client_id","admin_id","title","description","status","due_date","start_date"];
    public function client() { return $this->belongsTo(Client::class); }
    public function tasks() { return $this->hasMany(ProjectTask::class); }
    public function messages() { return $this->hasMany(ProjectMessage::class); }
}
