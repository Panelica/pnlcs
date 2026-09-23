<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientSsoToken extends Model
{
    protected $fillable = ['token_hash', 'user_id', 'client_id', 'admin_id', 'redirect_path', 'expires_at', 'used_at'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
