<?php

namespace App\Console\Commands;

use App\Models\DynamicTranslation;
use App\Translation\LangFileWriter;
use App\Translation\OfficialTranslationRepository;
use App\Translation\TranslationCacheManager;
use Illuminate\Console\Command;

/**
 * Copy a language's database translations into lang/<locale>/*.php.
 *
 * The editor and "Translate with AI" store their results in the database,
 * which only that one install can see. Written into the language files, the
 * same text can be committed and sent upstream, and every install gets it
 * with the next update.
 *
 * The files are edited, not regenerated: each value is replaced where it
 * stands, so comments, order and layout stay as they are and the diff shows
 * only the texts that changed. A key the file does not have yet is added at
 * the end of it. Only keys that English ships in its files are written; a
 * text that exists only in the database has no file to belong to.
 */
class LangWriteCommand extends Command
{
    protected $signature = 'pnlcs:lang-write
        {locale : The language code, e.g. tr}
        {--group= : Only this file (admin, client, auth, ...)}
        {--clear : Remove the database rows once they are in the files}
        {--dry-run : Show what would change, write nothing}';

    protected $description = 'Write the database translations of a language into its lang/<locale> files';

    public function handle(OfficialTranslationRepository $official, LangFileWriter $writer): int
    {
        $locale = (string) $this->argument('locale');
        if (! preg_match('/^[a-z]{2}(-[a-z]{2})?$/', $locale) || $locale === 'en') {
            $this->error('Give a language code other than en, e.g. tr or pt-br.');

            return self::FAILURE;
        }

        $english = $official->forLocale('en');
        $current = $official->forLocale($locale);

        $rows = DynamicTranslation::where('language', $locale)
            ->whereNotNull('value')->where('value', '!=', '')
            ->when($this->option('group'), fn ($q, $group) => $q->where('group', $group))
            ->orderBy('group')->orderBy('key')
            ->get(['id', 'group', 'key', 'value']);

        $byGroup = [];
        $notInEnglish = 0;
        foreach ($rows as $row) {
            if (! isset($english[$row->group][$row->key])) {
                $notInEnglish++;

                continue;
            }
            $byGroup[$row->group][$row->key] = $row;
        }

        $changed = 0;
        $written = [];
        foreach ($byGroup as $group => $items) {
            $values = [];
            foreach ($items as $key => $row) {
                if (($current[$group][$key] ?? null) !== $row->value) {
                    $values[$key] = $row->value;
                }
            }

            $target = lang_path("{$locale}/{$group}.php");
            $template = lang_path("en/{$group}.php");

            if ($values !== []) {
                $this->line(sprintf('  %-12s %d text(s)', $group.'.php', count($values)));
                if (! $this->option('dry-run')) {
                    $writer->write($target, $template, $values);
                }
                $changed += count($values);
            }

            $written[$group] = $items;
        }

        if ($notInEnglish > 0) {
            $this->warn("{$notInEnglish} database text(s) left alone: English has no such key in its files.");
        }

        if ($this->option('dry-run')) {
            $this->info("{$changed} text(s) would be written to lang/{$locale}. Nothing was changed.");

            return self::SUCCESS;
        }

        $this->info("{$changed} text(s) written to lang/{$locale}.");

        if ($this->option('clear')) {
            $ids = collect($written)->flatten()->pluck('id')->all();
            DynamicTranslation::whereIn('id', $ids)->delete();
            $this->info(count($ids).' database row(s) removed; the files now carry these texts.');
        }

        TranslationCacheManager::flushLocale($locale);

        return self::SUCCESS;
    }
}
