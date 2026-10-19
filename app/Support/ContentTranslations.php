<?php

namespace App\Support;

use App\Models\Language;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Translations of content an operator writes (an announcement): the record's
 * own text is the default language's, and a translation row holds another
 * active language's.
 */
class ContentTranslations
{
    /** Every active language but the default one, whose text is the record's own. */
    public static function languages(): Collection
    {
        return Language::active()
            ->where('code', '!=', LocaleUrl::defaultLocale())
            ->orderBy('sort_order')->get();
    }

    /**
     * Save the translations a form sent, keyed by language: filled in → kept,
     * the first field left empty → removed, so a reader in that language
     * falls back to the default text. Only the languages above are stored.
     *
     * @param  array<string, mixed>  $sent  language => [field => value]
     * @param  array<int, string>  $fields  the columns; the first is the one that must be filled
     */
    public static function save(HasMany $relation, array $sent, array $fields): void
    {
        $required = $fields[0];
        $foreignKey = $relation->getForeignKeyName();
        // A fresh query each time: where() on the relation itself would pile up.
        $rows = fn () => $relation->getRelated()->newQuery()->where($foreignKey, $relation->getParentKey());

        foreach (self::languages()->pluck('code') as $code) {
            $row = (array) ($sent[$code] ?? []);
            if (trim((string) ($row[$required] ?? '')) === '') {
                $rows()->where('language', $code)->delete();

                continue;
            }
            $values = [$foreignKey => $relation->getParentKey()];
            foreach ($fields as $field) {
                $values[$field] = isset($row[$field]) ? (string) $row[$field] : null;
            }
            $rows()->updateOrCreate(['language' => $code], $values);
        }
    }
}
