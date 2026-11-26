<?php

namespace App\Listeners;

use App\Models\Setting;
use Illuminate\Mail\Events\MessageSending;

/**
 * Put the registered company name at the front of every outgoing subject.
 *
 * Mail clients sort by subject, so a prefix groups everything the business
 * sends together and makes a transactional email look less like a stranger's
 * message. A suffix in the templates would have to be repeated in every
 * language and every custom template; this runs after the template has set
 * its subject, so new mailables and operator-written subjects are covered too.
 */
class BrandEmailSubject
{
    private const SEPARATOR = ' — ';

    public function handle(MessageSending $event): void
    {
        $subject = trim((string) $event->message->getSubject());

        if ($subject === '') {
            return;
        }

        $brand = $this->prefix();

        if ($brand === '') {
            return;
        }

        $brandLower = mb_strtolower($brand);
        $subjectLower = mb_strtolower($subject);

        // Already in the right place: do not add a second copy.
        if (str_starts_with($subjectLower, $brandLower)) {
            return;
        }

        $subject = $this->stripLeadingBrand($subject);
        $subject = $this->stripTrailingBrand($subject);
        $subject = trim($subject);

        $event->message->subject($subject === '' ? $brand : $brand.self::SEPARATOR.$subject);
    }

    /**
     * The name that goes in front.
     *
     * A white-label name, where the operator set one, comes first: that is
     * what the shipped contract says customer-facing text carries ("so the
     * customer-facing site carries your brand, not ours",
     * docs/guides/themes-and-branding.md), and it is what company_name()
     * already honours everywhere else. Only without one is the registered
     * name used, which is the point of this listener - an email that names
     * the business rather than nobody.
     */
    private function prefix(): string
    {
        try {
            $whiteLabel = trim((string) Setting::get('whitelabel_company_name', ''));
        } catch (\Throwable) {
            $whiteLabel = '';
        }

        return $whiteLabel !== '' ? $whiteLabel : trim(company_legal_name());
    }

    /** A subject starting with another configured brand loses it. */
    private function stripLeadingBrand(string $subject): string
    {
        $lower = mb_strtolower($subject);

        foreach ($this->brands() as $brand) {
            $brandLower = mb_strtolower($brand);

            if (! str_starts_with($lower, $brandLower)) {
                continue;
            }

            $rest = mb_substr($subject, mb_strlen($brand));
            $rest = preg_replace('/^\s*(?:—|–|-|\||:)\s*/u', '', $rest) ?? $rest;

            return trim($rest);
        }

        return $subject;
    }

    /** A subject ending in another configured brand loses it too. */
    private function stripTrailingBrand(string $subject): string
    {
        $lower = mb_strtolower($subject);

        foreach ($this->brands() as $brand) {
            $brandLower = mb_strtolower($brand);

            if (! str_ends_with($lower, $brandLower)) {
                continue;
            }

            $prefix = mb_substr($subject, 0, mb_strlen($subject) - mb_strlen($brand));
            $prefix = preg_replace('/\s*(?:—|–|-|\||:)\s*$/u', '', $prefix) ?? $prefix;

            return trim($prefix);
        }

        return $subject;
    }

    /**
     * Names that mean "this email is already from us". The longest match is
     * tried first so a longer configured name is not reduced to its prefix.
     *
     * @return list<string>
     */
    private function brands(): array
    {
        try {
            $companyName = (string) Setting::get('CompanyName', '');
            $whiteLabel = (string) Setting::get('whitelabel_company_name', '');
        } catch (\Throwable) {
            $companyName = '';
            $whiteLabel = '';
        }

        $values = [
            company_legal_name(),
            $companyName,
            $whiteLabel,
            company_name(),
        ];

        return collect($values)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique(fn ($value) => mb_strtolower($value))
            ->sortByDesc(fn ($value) => mb_strlen($value))
            ->values()
            ->all();
    }
}
