<?php

namespace App\Services\Updates\FileSets;

/** A version held in memory: path => content. Links are written "link:<target>". */
final class ArrayFileSet implements FileSet
{
    /** @param array<string, string> $files */
    public function __construct(private readonly array $files) {}

    public function entries(): array
    {
        return array_map(
            fn (string $content) => Fingerprint::isLink($content) ? $content : Fingerprint::ofContent($content),
            $this->files,
        );
    }

    public function read(string $path): ?string
    {
        if (! isset($this->files[$path])) {
            return null;
        }

        return Fingerprint::isLink($this->files[$path]) ? substr($this->files[$path], 5) : $this->files[$path];
    }
}
