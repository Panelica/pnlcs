<?php

namespace App\View\Composers;

use App\Models\Language;
use Illuminate\View\View;

class LanguageComposer
{
    public function compose(View $view): void
    {
        try {
            $activeLanguages = Language::where('is_active', true)
                ->orderBy('sort_order')
                ->get();
        } catch (\Throwable $e) {
            $activeLanguages = collect();
        }

        $currentLocale = app()->getLocale();
        $currentLanguage = $activeLanguages->firstWhere('code', $currentLocale);

        $view->with([
            'activeLanguages' => $activeLanguages,
            'currentLocale' => $currentLocale,
            'currentLocaleName' => $currentLanguage?->native_name ?? 'English',
            'textDirection' => $currentLanguage?->direction ?? 'ltr',
        ]);
    }
}
