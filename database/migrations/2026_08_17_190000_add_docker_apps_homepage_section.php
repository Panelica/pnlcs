<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * The shop window for the app catalogue, as a homepage section.
 *
 * Registered rather than hard-coded so it behaves like every other section:
 * switched on or off, reordered, and its wording edited from the admin
 * Homepage screen. It arrives disabled - an install with no Panelica server
 * attached should not show an apps section on its front page - and the operator
 * turns it on when there is something to show.
 */
return new class extends Migration
{
    private const SLUG = 'docker-apps';

    public function up(): void
    {
        if (DB::table('homepage_sections')->where('slug', self::SLUG)->exists()) {
            return;
        }

        $last = (int) DB::table('homepage_sections')->max('sort_order');

        DB::table('homepage_sections')->insert([
            'slug' => self::SLUG,
            'title' => 'One-Click Apps',
            'sort_order' => $last + 1,
            'is_enabled' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Left empty on purpose: the section falls back to translated defaults,
        // so it reads correctly in every language until an operator overrides it.
        foreach ([
            ['limit', '12', 'text'],
            ['title', '', 'text'],
            ['subtitle', '', 'text'],
            ['cta_text', '', 'text'],
            ['cta_url', '', 'text'],
            ['point_1', '', 'text'],
            ['point_2', '', 'text'],
            ['point_3', '', 'text'],
        ] as [$key, $value, $type]) {
            DB::table('homepage_content')->insert([
                'section_slug' => self::SLUG,
                'content_key' => $key,
                'content_value' => $value,
                'content_type' => $type,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('homepage_content')->where('section_slug', self::SLUG)->delete();
        DB::table('homepage_sections')->where('slug', self::SLUG)->delete();
    }
};
