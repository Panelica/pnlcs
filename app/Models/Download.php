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

    /** Where uploaded files are kept: the private disk, never a public URL. */
    public const DISK = 'local';

    /**
     * An uploaded file (type "file", location = its path on the private disk)
     * rather than a link (type "link", location = the address).
     */
    public function isStoredFile(): bool
    {
        return $this->type === 'file';
    }

    /** The uploaded file's name as the customer receives it. */
    public function fileName(): string
    {
        return basename((string) $this->location);
    }

    public function deleteStoredFile(): void
    {
        if ($this->isStoredFile() && filled($this->location)) {
            \Illuminate\Support\Facades\Storage::disk(self::DISK)->delete($this->location);
        }
    }

    /** The products whose owners may download this; none means everyone signed in. */
    public function products()
    {
        return $this->belongsToMany(Product::class, 'download_product');
    }

    /**
     * Downloads a client may see: published, and either open to everyone or
     * tied to a product the client has an active service of.
     */
    public function scopeAvailableTo($query, ?int $clientId)
    {
        return $query->where('hidden', false)->where(fn ($q) => $q
            ->whereDoesntHave('products')
            ->orWhereHas('products', fn ($p) => $p->whereIn('products.id', Service::where('client_id', $clientId ?? 0)
                ->where('status', \App\Enums\ServiceStatus::Active->value)->select('product_id'))));
    }
}
