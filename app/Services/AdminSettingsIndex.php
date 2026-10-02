<?php

namespace App\Services;

/**
 * The sections of Admin > Settings > General and the words in them, for the
 * admin bar's search.
 *
 * Read from the settings view itself rather than kept as a list: each card is
 * a <div class="card" id="..."> whose header and labels are translation keys,
 * so a card or field added there is found without anyone remembering to add
 * it here. Labels are translated in the admin's own language.
 */
class AdminSettingsIndex
{
    /** @var array<string, list<array{anchor: string, title: string, keys: list<string>}>> */
    private static array $cards = [];

    /**
     * @return list<array{title: string, subtitle: string, url: string}>
     */
    public function search(string $query, int $limit = 5): array
    {
        $needle = mb_strtolower(trim($query));
        if (mb_strlen($needle) < AdminSearch::MIN_LENGTH) {
            return [];
        }

        $page = __('admin.search.settings_page');
        $base = route('admin.settings.general');
        $found = [];

        foreach ($this->cards() as $card) {
            $title = (string) __($card['title']);
            $match = null;

            if (str_contains(mb_strtolower($title), $needle)) {
                $match = '';
            } else {
                foreach ($card['keys'] as $key) {
                    $text = trim(strip_tags((string) __($key)));
                    if ($text !== '' && $text !== $key && str_contains(mb_strtolower($text), $needle)) {
                        $match = mb_strimwidth($text, 0, 90, '…');
                        break;
                    }
                }
            }

            if ($match !== null) {
                $found[] = ['title' => $page.' › '.$title, 'subtitle' => $match, 'url' => $base.'#'.$card['anchor']];
            }
            if (count($found) >= $limit) {
                break;
            }
        }

        return $found;
    }

    /** @return list<array{anchor: string, title: string, keys: list<string>}> */
    public function cards(): array
    {
        $path = resource_path('views/admin/settings/general.blade.php');
        $stamp = is_file($path) ? $path.':'.filemtime($path) : '';

        if (! isset(self::$cards[$stamp])) {
            self::$cards[$stamp] = $stamp === '' ? [] : $this->parse((string) file_get_contents($path));
        }

        return self::$cards[$stamp];
    }

    /** @return list<array{anchor: string, title: string, keys: list<string>}> */
    private function parse(string $view): array
    {
        $pattern = '/<div class="card"[^>]*\bid="([^"]+)"[^>]*>\s*<div class="card-header"><strong>\{\{ __\(\'([a-z0-9_.]+)\'\) \}\}<\/strong>/';
        preg_match_all($pattern, $view, $heads, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $cards = [];
        foreach ($heads as $i => $head) {
            $start = $head[0][1];
            $end = $heads[$i + 1][0][1] ?? strlen($view);
            preg_match_all("/__\\('([a-z0-9_.]+)'/", substr($view, $start, $end - $start), $keys);

            $cards[] = [
                'anchor' => $head[1][0],
                'title' => $head[2][0],
                'keys' => array_values(array_unique(array_diff($keys[1], [$head[2][0]]))),
            ];
        }

        return $cards;
    }
}
