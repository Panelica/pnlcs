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

        $brand = trim(company_legal_name());

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

    /** A subject starting with "BoxHost — ..." loses the old brand. */
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

    /** A subject ending in " - BoxHost" loses the old brand too. */
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
     * tried first so "24Box Hosting" is not reduced to bare "24Box".
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
            '24Box Hosting',
            '24Box',
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
