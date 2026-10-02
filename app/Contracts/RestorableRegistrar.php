<?php

namespace App\Contracts;

use App\Models\Domain;

/**
 * A registrar module that can bring a domain back out of the registry's
 * redemption period. Optional: a module without it leaves the restore to an
 * admin, who gets a to-do for it (DomainRestoreService).
 */
interface RestorableRegistrar
{
    /** @return array{success: bool, message: string} */
    public function restore(Domain $domain): array;
}
