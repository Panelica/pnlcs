<?php

namespace App\Contracts;

use App\Models\Service;

/**
 * A server module whose accounts can take more domains after they are created.
 *
 * The client area offers "set up on my hosting" for a domain when one of the
 * customer's services runs on a module that implements this.
 */
interface HostsAccountDomains
{
    /**
     * The domains on the account, as [id => name]. Empty when the service is
     * not linked to an account or the panel cannot be asked.
     *
     * @return array<string, string>
     */
    public function accountDomains(Service $service): array;

    /**
     * Add a domain to the account. A domain already there counts as success,
     * so running it twice is harmless.
     *
     * @return array{success: bool, message: string, data: array}
     */
    public function createAccountDomain(Service $service, string $domain): array;
}
