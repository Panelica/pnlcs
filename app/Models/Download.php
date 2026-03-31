<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Download extends Model {
    protected $table = "downloads";
    protected $fillable = ["category_id", "type", "title", "description", "download_count", "location", "clients_only", "hidden"];

    public function category() { return $this->belongsTo(DownloadCategory::class); }
}
