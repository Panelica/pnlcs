<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Download extends Model {
    use HasFactory;

    protected $table = "downloads";
    protected $fillable = ["category_id", "type", "title", "description", "download_count", "location", "clients_only", "hidden"];

    public function category() { return $this->belongsTo(DownloadCategory::class); }
}
