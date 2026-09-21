<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Turkish for the public Docker-apps section: the 20 keys in the "sections"
 * group that were only ever seeded in English.
 *
 * Its own migration rather than a branch inside the client one, because every
 * translation seed in this directory handles exactly one group - the group is
 * a constant, rows() is a flat [key => …] map, and down() is a single
 * whereIn. Threading a third element through rows() to carry the group would
 * change that shape for the eight migrations that already share it and buy
 * nothing: the two sets have no keys, no glossary and no reviewer in common.
 *
 * This section was rewritten by 2026_08_17_210000_seed_docker_apps_section_keys
 * and 2026_08_17_260000_expand_docker_apps_showcase, which moved the English
 * on: sections.apps.point_isolation used to be the bold heading and is now the
 * paragraph under it, with point_isolation_t holding the heading. lang/tr
 * still carries the old short Turkish for those keys, so a Turkish visitor
 * read a heading where a paragraph belongs. The rows here are translated from
 * the current English and, being database rows, take precedence over the file
 * (app/Translation/DbTranslationLoader.php).
 *
 * GLOSSARY, continued from 2026_09_20_100000:
 *   app -> uygulama, plan -> paket, domain -> alan adı, catalogue -> katalog,
 *   isolation -> izolasyon. Product names untouched: WordPress, n8n, Docker,
 *   SSH, Compose. Polite address (-iniz) throughout.
 *
 * Turkish only, exists()-guarded so an operator's own wording survives, and a
 * symmetric down() - the same shape as
 * 2026_08_28_130000_translate_runtime_app_keys_tr_pl_zh.
 */
return new class extends Migration
{
    private const GROUP = 'sections';

    /** [key => [en, tr]] - the English is the source that was translated. */
    private function rows(): array
    {
        return [
            'apps.and_more' => ['+ :count more in the catalogue', '+ katalogda :count uygulama daha'],
            'apps.cta' => ['See the plans', 'Paketleri inceleyin'],
            'apps.cta_learn' => ['How it works', 'Nasıl çalışır'],
            'apps.eyebrow' => ['Included with every plan', 'Her pakete dahil'],
            'apps.featured' => ['Popular choice', 'Popüler seçim'],

            // The three selling points: *_t is the bold heading, the bare key
            // is the sentence under it (resources/views/sections/docker-apps.blade.php:30-32).
            'apps.point_included' => [
                'The catalogue comes with the plan. Run one app or fill the plan - the price is the resources, not the number of apps.',
                'Katalog paketle birlikte gelir. İster tek uygulama çalıştırın ister paketi doldurun; ücretini ödediğiniz kaynaklardır, uygulama sayısı değil.',
            ],
            'apps.point_included_t' => ['No licence, no per-app fee', 'Lisans yok, uygulama başına ücret yok'],
            'apps.point_isolation' => [
                "Every app runs in your own kernel-enforced slice. Nobody else's traffic can eat your memory, and yours cannot escape into theirs.",
                'Her uygulama, çekirdek tarafından güvence altına alınan kendi kaynak diliminizde çalışır. Başkasının trafiği sizin belleğinizi tüketemez, sizinki de onlarınkine taşamaz.',
            ],
            'apps.point_isolation_t' => ['Real isolation, not shared roulette', 'Gerçek izolasyon, paylaşımlı sunucu kumarı değil'],
            'apps.point_oneclick' => [
                'Pick an app, give it a name, and point one of your domains at it. No compose files, no server to rent, no SSH.',
                'Bir uygulama seçin, adını verin ve alan adlarınızdan birini ona yönlendirin. Compose dosyası yok, kiralanacak sunucu yok, SSH yok.',
            ],
            'apps.point_oneclick_t' => ['One click, then it is yours', 'Tek tık, gerisi sizin'],

            // The three steps: *_t is the step heading.
            'apps.step1' => [
                'Plans differ by memory, CPU and disk. That is your budget for everything you run.',
                'Paketler bellek, CPU ve disk bakımından ayrışır. Çalıştıracağınız her şeyin bütçesi budur.',
            ],
            'apps.step1_t' => ['Choose a plan', 'Bir paket seçin'],
            'apps.step2' => [
                'Search the catalogue, check what each app needs, and install it from your control panel.',
                'Katalogda arayın, uygulamanın neye ihtiyaç duyduğuna bakın ve kontrol panelinizden kurun.',
            ],
            'apps.step2_t' => ['Install what you need', 'İhtiyacınız olanı kurun'],
            'apps.step3' => [
                'Point one of your domains at the app and it is live, with certificates handled for you.',
                'Alan adlarınızdan birini uygulamaya yönlendirin; sertifikası sizin için halledilmiş olarak yayına girer.',
            ],
            'apps.step3_t' => ['Put it on your domain', 'Alan adınızda yayınlayın'],

            'apps.subtitle' => [
                'WordPress, n8n, databases, dashboards - :count applications, installed in one click and running inside your own account limits.',
                'WordPress, n8n, veritabanları, panolar - tek tıkla kurulan ve kendi hesap sınırlarınızın içinde çalışan :count uygulama.',
            ],
            'apps.title' => [
                'Run the apps you want, on hosting that keeps them apart',
                'İstediğiniz uygulamaları, onları birbirinden ayıran bir hostingde çalıştırın',
            ],

            // Matches lang/tr/sections.php (nav.docker_hosting).
            'nav.docker_hosting' => ['Docker Apps', 'Docker Uygulamaları'],
        ];
    }

    public function up(): void
    {
        $now = now();

        foreach ($this->rows() as $key => [, $turkish]) {
            $exists = DB::table('dynamic_translations')
                ->where('language', 'tr')->where('group', self::GROUP)->where('key', $key)->exists();

            if (! $exists) {
                DB::table('dynamic_translations')->insert([
                    'language' => 'tr', 'group' => self::GROUP, 'key' => $key, 'value' => $turkish,
                    'is_auto_translated' => false, 'is_reviewed' => true,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        try {
            Cache::forget('translations:tr:'.self::GROUP);
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
        DB::table('dynamic_translations')
            ->where('language', 'tr')->where('group', self::GROUP)
            ->whereIn('key', array_keys($this->rows()))
            ->delete();

        try {
            Cache::forget('translations:tr:'.self::GROUP);
        } catch (\Throwable $e) {
        }
    }
};
