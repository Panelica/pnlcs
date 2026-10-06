<?php

namespace App\Services\Updates;

/** One published release, as the release index lists it. */
final class Release
{
    /** @param array<string, string> $assets file name => download URL */
    public function __construct(
        public readonly Version $version,
        public readonly string $tag,
        public readonly bool $preRelease,
        public readonly string $notes,
        public readonly ?string $publishedAt,
        public readonly ?string $url,
        public readonly array $assets,
    ) {}

    public function packageName(): string
    {
        return "pnlcs-{$this->version}.tar.gz";
    }

    public function statementName(): string
    {
        return "pnlcs-{$this->version}.release.json";
    }

    public function asset(string $name): ?string
    {
        return $this->assets[$name] ?? null;
    }

    public function isComplete(): bool
    {
        return $this->asset($this->packageName()) && $this->asset($this->statementName()) && $this->asset($this->statementName().'.sig');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'version' => (string) $this->version,
            'tag' => $this->tag,
            'pre_release' => $this->preRelease,
            'notes' => $this->notes,
            'published_at' => $this->publishedAt,
            'url' => $this->url,
        ];
    }
}
