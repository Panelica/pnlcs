<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DownloadCategory extends Model {
    use HasFactory;

    protected $table = "download_categories";
    protected $fillable = ["parent_id", "name", "description", "hidden"];

    public function downloads() { return $this->hasMany(Download::class); }
}
