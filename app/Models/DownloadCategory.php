<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DownloadCategory extends Model {
    protected $table = "download_categories";
    protected $fillable = ["parent_id", "name", "description", "hidden"];

    public function downloads() { return $this->hasMany(Download::class); }
}
