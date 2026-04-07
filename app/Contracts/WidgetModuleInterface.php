<?php

namespace App\Contracts;

interface WidgetModuleInterface
{
    public function getTitle(): string;
    public function getDescription(): string;

    /** Column span: 1 = quarter, 2 = half, 3 = three-quarter, 4 = full */
    public function getColumns(): int;

    /** Sort weight: lower = higher position. Default 100. */
    public function getWeight(): int;

    /** Admin permission required to view this widget */
    public function getPermission(): ?string;

    /** Cache TTL in seconds (0 = no cache) */
    public function getCacheTtl(): int;

    /** Fetch data for the widget */
    public function getData(): array;

    /** Render HTML output */
    public function render(array $data): string;
}
