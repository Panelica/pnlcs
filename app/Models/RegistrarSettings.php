<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class RegistrarSettings extends Model {
    protected $table    = "registrar_settings";
    protected $fillable = ["registrar", "setting", "value"];
}
