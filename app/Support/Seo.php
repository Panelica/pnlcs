<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Str;
use Throwable;

/**
 * What search engines and link previews read about a page: its description,
 * the image shown when the link is shared, and the shop's X (Twitter)
 * account. Set under Setup > General > Search engines and sharing; a page can
 * give its own description (@section('meta_description', ...)).
 */
class Seo
{
    public const KEYS = ['SeoDescription', 'SeoShareImage', 'SeoTwitter'];

    /** The page's description, else the shop's, plain text, at most 160 characters. */
    public static function description(?string $page = null): string
    {
        $text = trim((string) ($page ?? ''));
        if ($text === '') {
            $text = self::setting('SeoDescription');
        }

        return Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')))), 160, '…');
    }

    /** The image a shared link shows: the one set for sharing, else the logo; absolute. Empty when there is neither. */
    public static function image(): string
    {
        $image = self::setting('SeoShareImage') ?: self::setting('custom_logo_path');

        return $image === '' ? '' : (preg_match('#^https?://#i', $image) ? $image : url($image));
    }

    /** The shop's X account as "@name", or empty. */
    public static function twitter(): string
    {
        $handle = ltrim(trim(self::setting('SeoTwitter')), '@');

        return preg_match('/^\w{1,30}$/', $handle) ? '@'.$handle : '';
    }

    /** The page language as Open Graph writes it: en_GB is not needed, a language is enough. */
    public static function ogLocale(): string
    {
        return str_replace('-', '_', (string) app()->getLocale());
    }

    private static function setting(string $key): string
    {
        try {
            return trim((string) Setting::get($key, ''));
        } catch (Throwable) {
            return '';
        }
    }
}
