<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Download extends Model
{
    use HasFactory;

    protected $table = 'downloads';

    protected $fillable = ['category_id', 'type', 'title', 'description', 'download_count', 'location', 'clients_only', 'hidden'];

    protected function casts(): array
    {
        return ['hidden' => 'boolean', 'clients_only' => 'boolean'];
    }

    public function category()
    {
        return $this->belongsTo(DownloadCategory::class);
    }
}
