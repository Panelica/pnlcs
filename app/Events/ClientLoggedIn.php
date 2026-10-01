<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A login finished signing in to the client area (after the second factor,
 * when there is one). Bridged to the UserLogin and ClientLogin hook points.
 */
class ClientLoggedIn
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user,
        public string $method = 'password',
        public ?string $ipAddress = null,
        public bool $newDevice = false,
    ) {}
}
