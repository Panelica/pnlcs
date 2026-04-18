<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ModuleLog extends Model {
    protected $table = "module_logs";
    protected $fillable = ["module", "action", "request", "response", "service_id"];

}
