<?php

namespace App\Contracts;

/**
 * Optional capability for an addon that contributes an admin navigation link.
 *
 * Unlike sidebar(), which only fills the Extensions menu, an entry declares
 * its location (billing or extensions). AddonManager
 * collects only entries of active addons and the layout renders each slot.
 */
interface AdminMenuProvider
{
    /**
     * @return array<int, array{
     *     location: string,
     *     label: string,
     *     url: string,
     *     permission?: string|null,
     *     order?: int,
     *     children?: array<int, array{label: string, url: string, permission?: string|null}>
     * }>
     */
    public function adminMenu(): array;
}
