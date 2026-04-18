<?php

namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Seeder;

class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        $languages = [
            ['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'flag_code' => 'gb', 'direction' => 'ltr', 'is_active' => true, 'is_default' => true, 'sort_order' => 1],
            ['code' => 'tr', 'name' => 'Turkish', 'native_name' => 'Türkçe', 'flag_code' => 'tr', 'direction' => 'ltr', 'is_active' => false, 'sort_order' => 2],
            ['code' => 'de', 'name' => 'German', 'native_name' => 'Deutsch', 'flag_code' => 'de', 'direction' => 'ltr', 'is_active' => false, 'sort_order' => 3],
            ['code' => 'fr', 'name' => 'French', 'native_name' => 'Français', 'flag_code' => 'fr', 'direction' => 'ltr', 'is_active' => false, 'sort_order' => 4],
            ['code' => 'es', 'name' => 'Spanish', 'native_name' => 'Español', 'flag_code' => 'es', 'direction' => 'ltr', 'is_active' => false, 'sort_order' => 5],
            ['code' => 'pt-br', 'name' => 'Portuguese (Brazil)', 'native_name' => 'Português (Brasil)', 'flag_code' => 'br', 'direction' => 'ltr', 'is_active' => false, 'sort_order' => 6],
            ['code' => 'it', 'name' => 'Italian', 'native_name' => 'Italiano', 'flag_code' => 'it', 'direction' => 'ltr', 'is_active' => false, 'sort_order' => 7],
            ['code' => 'nl', 'name' => 'Dutch', 'native_name' => 'Nederlands', 'flag_code' => 'nl', 'direction' => 'ltr', 'is_active' => false, 'sort_order' => 8],
            ['code' => 'ru', 'name' => 'Russian', 'native_name' => 'Русский', 'flag_code' => 'ru', 'direction' => 'ltr', 'is_active' => false, 'sort_order' => 9],
            ['code' => 'ja', 'name' => 'Japanese', 'native_name' => '日本語', 'flag_code' => 'jp', 'direction' => 'ltr', 'is_active' => false, 'sort_order' => 10],
            ['code' => 'ko', 'name' => 'Korean', 'native_name' => '한국어', 'flag_code' => 'kr', 'direction' => 'ltr', 'is_active' => false, 'sort_order' => 11],
            ['code' => 'zh', 'name' => 'Chinese', 'native_name' => '中文', 'flag_code' => 'cn', 'direction' => 'ltr', 'is_active' => false, 'sort_order' => 12],
            ['code' => 'ar', 'name' => 'Arabic', 'native_name' => 'العربية', 'flag_code' => 'sa', 'direction' => 'rtl', 'is_active' => false, 'sort_order' => 13],
            ['code' => 'fa', 'name' => 'Persian', 'native_name' => 'فارسی', 'flag_code' => 'ir', 'direction' => 'rtl', 'is_active' => false, 'sort_order' => 14],
            ['code' => 'he', 'name' => 'Hebrew', 'native_name' => 'עברית', 'flag_code' => 'il', 'direction' => 'rtl', 'is_active' => false, 'sort_order' => 15],
            ['code' => 'pl', 'name' => 'Polish', 'native_name' => 'Polski', 'flag_code' => 'pl', 'direction' => 'ltr', 'is_active' => false, 'sort_order' => 16],
            ['code' => 'sv', 'name' => 'Swedish', 'native_name' => 'Svenska', 'flag_code' => 'se', 'direction' => 'ltr', 'is_active' => false, 'sort_order' => 17],
            ['code' => 'no', 'name' => 'Norwegian', 'native_name' => 'Norsk', 'flag_code' => 'no', 'direction' => 'ltr', 'is_active' => false, 'sort_order' => 18],
            ['code' => 'da', 'name' => 'Danish', 'native_name' => 'Dansk', 'flag_code' => 'dk', 'direction' => 'ltr', 'is_active' => false, 'sort_order' => 19],
            ['code' => 'fi', 'name' => 'Finnish', 'native_name' => 'Suomi', 'flag_code' => 'fi', 'direction' => 'ltr', 'is_active' => false, 'sort_order' => 20],
            ['code' => 'el', 'name' => 'Greek', 'native_name' => 'Ελληνικά', 'flag_code' => 'gr', 'direction' => 'ltr', 'is_active' => false, 'sort_order' => 21],
            ['code' => 'cs', 'name' => 'Czech', 'native_name' => 'Čeština', 'flag_code' => 'cz', 'direction' => 'ltr', 'is_active' => false, 'sort_order' => 22],
            ['code' => 'hu', 'name' => 'Hungarian', 'native_name' => 'Magyar', 'flag_code' => 'hu', 'direction' => 'ltr', 'is_active' => false, 'sort_order' => 23],
            ['code' => 'ro', 'name' => 'Romanian', 'native_name' => 'Română', 'flag_code' => 'ro', 'direction' => 'ltr', 'is_active' => false, 'sort_order' => 24],
            ['code' => 'hr', 'name' => 'Croatian', 'native_name' => 'Hrvatski', 'flag_code' => 'hr', 'direction' => 'ltr', 'is_active' => false, 'sort_order' => 25],
            ['code' => 'uk', 'name' => 'Ukrainian', 'native_name' => 'Українська', 'flag_code' => 'ua', 'direction' => 'ltr', 'is_active' => false, 'sort_order' => 26],
            ['code' => 'et', 'name' => 'Estonian', 'native_name' => 'Eesti', 'flag_code' => 'ee', 'direction' => 'ltr', 'is_active' => false, 'sort_order' => 27],
            ['code' => 'az', 'name' => 'Azerbaijani', 'native_name' => 'Azərbaycan', 'flag_code' => 'az', 'direction' => 'ltr', 'is_active' => false, 'sort_order' => 28],
            ['code' => 'ca', 'name' => 'Catalan', 'native_name' => 'Català', 'flag_code' => 'es-ct', 'direction' => 'ltr', 'is_active' => false, 'sort_order' => 29],
            ['code' => 'mk', 'name' => 'Macedonian', 'native_name' => 'Македонски', 'flag_code' => 'mk', 'direction' => 'ltr', 'is_active' => false, 'sort_order' => 30],
        ];

        foreach ($languages as $lang) {
            Language::updateOrCreate(['code' => $lang['code']], $lang);
        }
    }
}
