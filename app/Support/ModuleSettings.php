<?php

namespace App\Support;

/**
 * The rules for a module's settings bag, shared by the settings screens and
 * the API's updatemoduleconfiguration so the two cannot drift apart.
 */
class ModuleSettings
{
    /** More keys than any module declares; anything past it is a tampered request. */
    public const MAX_KEYS = 50;

    /**
     * The names a module marks as secret, so a blank one can mean "keep".
     *
     * Modules describe their fields two ways: a list of ['name' => ...] rows
     * (gateways, registrars) or an array keyed by the field name (SSL).
     *
     * @return list<string>
     */
    public static function secretFieldNames(array $configFields): array
    {
        return collect($configFields)
            ->map(fn ($field, $key) => ['name' => $field['name'] ?? (is_string($key) ? $key : null), 'type' => $field['type'] ?? null])
            ->filter(fn ($field) => $field['type'] === 'password' && is_string($field['name']) && $field['name'] !== '')
            ->pluck('name')
            ->values()
            ->all();
    }

    /**
     * Bound a settings bag to what a module setting can actually be.
     *
     * Module-declared names are deliberately NOT used as a whitelist. A
     * registrar stores a "name" setting that no module declares (it is the
     * operator's own label), and a third-party module is free to read a
     * setting it does not advertise; whitelisting would make both permanently
     * unconfigurable.
     *
     * @return array<string, string>
     */
    public static function sanitise(array $settings, array $secretFields): array
    {
        $clean = [];

        foreach (array_slice($settings, 0, self::MAX_KEYS, true) as $key => $value) {
            if (! is_string($key) || ! preg_match('/^[A-Za-z0-9_.-]{1,64}$/', $key)) {
                continue;
            }

            // A string column takes a string; anything else is a tampered form.
            if (is_array($value) || is_object($value)) {
                continue;
            }

            $value = (string) ($value ?? '');

            // Secret fields are never rendered back, so a blank one means it
            // was not touched - not that the live key should be deleted.
            if (in_array($key, $secretFields, true) && trim($value) === '') {
                continue;
            }

            $clean[$key] = $value;
        }

        return $clean;
    }
}
