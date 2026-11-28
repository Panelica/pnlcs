<?php

namespace App\Contracts;

/**
 * Optional capability for an addon that publishes admin navigation links.
 *
 * An addon implementing this contract is represented only by adminMenu() in
 * the admin navigation. sidebar() is the legacy fallback for addons that do
 * not implement this contract and is shown in Extensions.
 *
 * Entries are published into one of the existing menu locations. They are
 * collected by AdminMenuRegistry, which filters inactive addons, permissions
 * and routes, then renders the location slot in the layout.
 */
interface AdminMenuProvider
{
    /**
     * @return array<int, array{
     *     location: string,
     *     label: string,
     *     route?: string,
     *     parameters?: array<string, mixed>,
     *     url?: string,
     *     permission?: string|null,
     *     order?: int,
     *     children?: array<int, array{
     *         label: string,
     *         route?: string,
     *         parameters?: array<string, mixed>,
     *         url?: string,
     *         permission?: string|null
     *     }>
     * }>
     */
    public function adminMenu(): array;
}
