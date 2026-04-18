<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ApiRole extends Model {
    protected $table = "api_roles";
    protected $fillable = ["name", "description", "permissions"];

}
