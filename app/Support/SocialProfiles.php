<?php

namespace App\Support;

use App\Models\Setting;
use Throwable;

/**
 * The shop's social media profiles, set under Setup > General > Search engines
 * and sharing. Themes show them (social_profiles(), partials.social-links)
 * and the home page names them to search engines (schema.org Organization,
 * sameAs), which is how a search for the shop can show its profiles.
 */
class SocialProfiles
{
    /** Network => setting key, in the order they are shown. */
    public const NETWORKS = [
        'x' => 'SocialX',
        'facebook' => 'SocialFacebook',
        'instagram' => 'SocialInstagram',
        'linkedin' => 'SocialLinkedin',
        'youtube' => 'SocialYoutube',
        'tiktok' => 'SocialTiktok',
        'threads' => 'SocialThreads',
        'medium' => 'SocialMedium',
    ];

    /** The networks' own names (brands, the same in every language). */
    public const NAMES = ['x' => 'X', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'tiktok' => 'TikTok', 'threads' => 'Threads', 'medium' => 'Medium'];

    /** @return array<string, string> network => address, only the ones set to a web address */
    public static function all(): array
    {
        $out = [];
        foreach (self::NETWORKS as $network => $key) {
            try {
                $url = trim((string) Setting::get($key, ''));
            } catch (Throwable) {
                $url = '';
            }
            if (preg_match('#^https?://[^\s"<>]+$#i', $url)) {
                $out[$network] = $url;
            }
        }

        return $out;
    }
}
