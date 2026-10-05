<?php

namespace App\Models;

use App\Casts\EncryptedValue;
use Illuminate\Database\Eloquent\Model;

class WhmcsImportConnection extends Model
{
    protected $fillable = [
        'name', 'host', 'port', 'database', 'username', 'password', 'prefix',
    ];

    protected function casts(): array
    {
        return [
            'password' => EncryptedValue::class,
            'port' => 'integer',
        ];
    }

    /**
     * The raw connection parameters the importer's connector reads.
     *
     * The password is decrypted here; nothing but the connector ever sees it,
     * and it is never echoed back to the browser.
     *
     * @return array{host: string, port: int, database: string, username: string, password: string, prefix: string}
     */
    public function config(): array
    {
        return [
            'host' => $this->host,
            'port' => $this->port ?: 3306,
            'database' => $this->database,
            'username' => $this->username,
            'password' => $this->password ?? '',
            'prefix' => $this->prefix ?: 'tbl',
        ];
    }
}
