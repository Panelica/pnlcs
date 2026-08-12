<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

return new class extends Migration
{
    /**
     * PNLCS resolves UI strings from dynamic_translations, so the shipped
     * lang/zh/*.php files are invisible to the running panel until they are in
     * that table. This copies every zh string into the table (skipping any key
     * that already has a value, so hand-edited overrides are never clobbered)
     * and corrects the language label. is_active is left untouched - an
     * administrator still chooses when to enable the locale.
     */
    public function up(): void
    {
        $dir = lang_path('zh');
        if (! File::isDirectory($dir)) {
            return;
        }

        foreach (File::files($dir) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $group = $file->getFilenameWithoutExtension();
            $values = require $file->getPathname();
            if (! is_array($values)) {
                continue;
            }
            foreach ($this->flatten($values) as $key => $value) {
                if (! is_string($value) || $value === '') {
                    continue;
                }
                $exists = DB::table('dynamic_translations')
                    ->where(['language' => 'zh', 'group' => $group, 'key' => $key])
                    ->exists();
                if ($exists) {
                    continue;
                }
                DB::table('dynamic_translations')->insert([
                    'language' => 'zh',
                    'group' => $group,
                    'key' => $key,
                    'value' => $value,
                    'is_auto_translated' => false,
                    'is_reviewed' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('languages')->where('code', 'zh')->update([
            'name' => 'Simplified Chinese',
            'native_name' => '简体中文',
        ]);
    }

    public function down(): void
    {
        DB::table('dynamic_translations')->where('language', 'zh')->delete();
        DB::table('languages')->where('code', 'zh')->update([
            'name' => 'Chinese',
            'native_name' => '中文',
        ]);
    }

    private function flatten(array $values, string $prefix = ''): array
    {
        $result = [];
        foreach ($values as $k => $v) {
            $key = $prefix === '' ? (string) $k : $prefix.'.'.$k;
            if (is_array($v)) {
                $result += $this->flatten($v, $key);
            } elseif (is_string($v)) {
                $result[$key] = $v;
            }
        }

        return $result;
    }
};
