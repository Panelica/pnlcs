<?php

namespace App\Contracts;

/**
 * Optional capability: a module that can verify its own connection to the
 * outside world and report the outcome — used by a "Test" button.
 *
 * Kept separate from the concrete module interfaces so any registrar (or
 * gateway) can opt in without the others having to implement it.
 */
interface TestsConnection
{
    /**
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array;
}
