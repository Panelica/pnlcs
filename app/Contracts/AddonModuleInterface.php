<?php

namespace App\Contracts;

use Illuminate\Http\Request;

interface AddonModuleInterface
{
    /** Unique addon identifier */
    public function getName(): string;

    /** Display name */
    public function getDisplayName(): string;

    /** Short description */
    public function getDescription(): string;

    /** Addon version */
    public function getVersion(): string;

    /** Author name */
    public function getAuthor(): string;

    /** Called when addon is activated */
    public function activate(): array;

    /** Called when addon is deactivated */
    public function deactivate(): array;

    /** Render addon admin page */
    public function output(Request $request): string;

    /** Sidebar menu items for this addon */
    public function sidebar(): array;

    /** Configuration fields */
    public function config(): array;

    /** Called on upgrade from previous version */
    public function upgrade(string $fromVersion): array;
}
