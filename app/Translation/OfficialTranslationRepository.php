<?php

namespace App\Translation;

use Illuminate\Support\Facades\File;

class OfficialTranslationRepository
{
    /**
     * Return the file-backed translations in the same group/key shape used by
     * dynamic_translations. Literal dotted keys intentionally win over an
     * equivalent nested key, matching Laravel's lookup behaviour.
     *
     * @return array<string, array<string, string>>
     */
    public function forLocale(string $locale): array
    {
        $directory = lang_path($locale);
        if (! File::isDirectory($directory)) {
            return [];
        }

        $translations = [];
        foreach (File::files($directory) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $values = require $file->getPathname();
            if (! is_array($values)) {
                continue;
            }

            $translations[$file->getFilenameWithoutExtension()] = $this->flatten($values);
        }

        ksort($translations);

        return $translations;
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<string, string>
     */
    private function flatten(array $values, string $prefix = ''): array
    {
        $result = [];

        foreach ($values as $key => $value) {
            $key = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (is_array($value)) {
                foreach ($this->flatten($value, $key) as $nestedKey => $nestedValue) {
                    $result[$nestedKey] = $nestedValue;
                }
            } elseif (is_string($value)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    public function count(string $locale): int
    {
        return array_sum(array_map('count', $this->forLocale($locale)));
    }
}
