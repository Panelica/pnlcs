<?php

namespace App\Services\Updates;

/**
 * A PNLCS version number: MAJOR.MINOR.PATCH, optionally -beta.N (or -dev on
 * an unreleased checkout). Ordered the way semantic versioning orders them:
 * 1.5.0-beta.2 < 1.5.0-beta.10 < 1.5.0.
 */
final class Version
{
    private function __construct(
        public readonly int $major,
        public readonly int $minor,
        public readonly int $patch,
        public readonly ?string $preRelease,
    ) {}

    public static function parse(string $value): ?self
    {
        $value = ltrim(trim($value), 'vV');

        if (! preg_match('/^(\d+)\.(\d+)\.(\d+)(?:-([0-9A-Za-z.-]+))?$/', $value, $m)) {
            return null;
        }

        return new self((int) $m[1], (int) $m[2], (int) $m[3], $m[4] ?? null);
    }

    /** The version of this installation, from the VERSION file at its root. */
    public static function installed(?string $root = null): ?self
    {
        $file = ($root ?? base_path()).'/VERSION';

        return is_file($file) ? self::parse((string) file_get_contents($file)) : null;
    }

    public function isPreRelease(): bool
    {
        return $this->preRelease !== null;
    }

    public function isDevelopment(): bool
    {
        return $this->preRelease !== null && str_starts_with($this->preRelease, 'dev');
    }

    public function compare(self $other): int
    {
        foreach (['major', 'minor', 'patch'] as $part) {
            if ($this->{$part} !== $other->{$part}) {
                return $this->{$part} <=> $other->{$part};
            }
        }

        // A release outranks every pre-release of the same number.
        if ($this->preRelease === null || $other->preRelease === null) {
            return ($this->preRelease === null) <=> ($other->preRelease === null);
        }

        $a = explode('.', $this->preRelease);
        $b = explode('.', $other->preRelease);

        for ($i = 0; $i < max(count($a), count($b)); $i++) {
            if (! isset($a[$i]) || ! isset($b[$i])) {
                return isset($a[$i]) <=> isset($b[$i]);
            }

            $numeric = ctype_digit($a[$i]) && ctype_digit($b[$i]);
            $cmp = $numeric ? ((int) $a[$i] <=> (int) $b[$i]) : strcmp($a[$i], $b[$i]);

            if ($cmp !== 0) {
                return $cmp <=> 0;
            }
        }

        return 0;
    }

    public function greaterThan(self $other): bool
    {
        return $this->compare($other) > 0;
    }

    /**
     * Whether this version satisfies a constraint such as ">=1.5 <2" (every
     * space-separated comparison must hold). A bare version means "exactly".
     * A constraint that cannot be read is treated as not satisfied: a module
     * that states its requirements wrongly is not assumed to fit.
     */
    public function satisfies(string $constraint): bool
    {
        $parts = preg_split('/\s+/', trim($constraint)) ?: [];

        if ($parts === [] || $parts === ['']) {
            return true;
        }

        foreach ($parts as $part) {
            if (! preg_match('/^(>=|<=|>|<|=|==)?v?(\d+(?:\.\d+){0,2})(-[0-9A-Za-z.-]+)?$/', $part, $m)) {
                return false;
            }

            $numbers = explode('.', $m[2]) + [1 => '0', 2 => '0'];
            $other = self::parse($numbers[0].'.'.$numbers[1].'.'.$numbers[2].($m[3] ?? ''));

            // Requirements are about releases: 1.6.0-beta.2 counts as 1.6.0.
            $self = new self($this->major, $this->minor, $this->patch, null);
            $cmp = $self->compare($other);

            $ok = match ($m[1] ?: '=') {
                '>=' => $cmp >= 0,
                '<=' => $cmp <= 0,
                '>' => $cmp > 0,
                '<' => $cmp < 0,
                default => $cmp === 0,
            };

            if (! $ok) {
                return false;
            }
        }

        return true;
    }

    public function __toString(): string
    {
        return $this->major.'.'.$this->minor.'.'.$this->patch.($this->preRelease !== null ? '-'.$this->preRelease : '');
    }
}
