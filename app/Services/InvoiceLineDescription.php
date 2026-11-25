<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\ServiceAddon;
use App\Models\Setting;
use Carbon\Carbon;

/**
 * Builds the wording of a recurring invoice line: the product, its domain, its
 * billing cycle and the period the line pays for. The operator chooses which
 * of the two period facts is printed; one setting drives every generator so a
 * renewal, an add-on and a domain cannot drift apart.
 */
class InvoiceLineDescription
{
    public const CYCLE = 'cycle';

    public const PERIOD = 'period';

    public const BOTH = 'cycle_and_period';

    public const SETTING = 'InvoiceLinePeriodDisplay';

    public function mode(): string
    {
        $mode = (string) Setting::get(self::SETTING, self::BOTH);

        return in_array($mode, [self::CYCLE, self::PERIOD, self::BOTH], true)
            ? $mode
            : self::BOTH;
    }

    /** A hosting line: product, optional domain, cycle/period by setting. */
    public function service(
        string $name,
        ?string $domain,
        string $cycle,
        Carbon $periodStart,
        Carbon $periodEnd,
        ?string $locale = null,
        ?string $configured = null
    ): string {
        $parts = [$this->withCycle($name, $cycle, $locale)];

        if (filled($domain)) {
            $parts[] = $domain;
        }

        if ($this->showsPeriod()) {
            $parts[] = $this->period($periodStart, $periodEnd);
        }

        $line = implode(' — ', $parts);

        return $configured ? $line.' ('.$configured.')' : $line;
    }

    /** An add-on line, with the optional prorated-first-period note. */
    public function addon(ServiceAddon $addon, Carbon $periodStart, Carbon $periodEnd, ?string $suffix = null, ?string $name = null): string
    {
        $name = 'Addon: '.($name ?: $addon->label());
        $line = $this->service(
            $name,
            $addon->service?->domain,
            $addon->billing_cycle ?: 'Monthly',
            $periodStart,
            $periodEnd,
            $addon->client?->language
        );

        return $suffix ? $line.' — '.$suffix : $line;
    }

    /** A domain renewal line. */
    public function domainRenewal(Domain $domain): string
    {
        $locale = $domain->client?->language;
        $start = $this->date($domain->next_due_date) ?? now()->startOfDay();
        $years = max(1, (int) ($domain->registration_period ?? 1));
        $end = $start->copy()->addYearsNoOverflow($years);

        return $this->domainLine(
            __('client.invoices.domain_renewal_line', ['domain' => $domain->domain], $locale),
            $years,
            $start,
            $end,
            $locale
        );
    }

    /** A domain registration or transfer line on the first order. */
    public function domainRegistration(Domain $domain, string $action): string
    {
        $locale = $domain->client?->language;
        $key = strtolower($action) === 'transfer'
            ? 'client.invoices.domain_transfer_line'
            : 'client.invoices.domain_registration_line';
        $start = $this->date($domain->registration_date) ?? now()->startOfDay();
        $years = max(1, (int) ($domain->registration_period ?? 1));
        $end = $this->date($domain->expiry_date) ?? $start->copy()->addYearsNoOverflow($years);

        return $this->domainLine(
            __($key, ['domain' => $domain->domain], $locale),
            $years,
            $start,
            $end,
            $locale
        );
    }

    private function domainLine(string $name, int $years, Carbon $start, Carbon $end, ?string $locale): string
    {
        if ($this->showsCycle()) {
            $name .= ' ('.$this->yearsLabel($years, $locale).')';
        }

        return $this->showsPeriod()
            ? $name.' — '.$this->period($start, $end)
            : $name;
    }

    private function withCycle(string $name, string $cycle, ?string $locale): string
    {
        return $this->showsCycle()
            ? $name.' ('.cycle_label($cycle, $locale).')'
            : $name;
    }

    private function showsCycle(): bool
    {
        return in_array($this->mode(), [self::CYCLE, self::BOTH], true);
    }

    private function showsPeriod(): bool
    {
        return in_array($this->mode(), [self::PERIOD, self::BOTH], true);
    }

    /** The period is stored as [start, end), but printed inclusively.
     *
     * The end passed by the generators is the next due date - the first day
     * the invoice does not cover. Printing it directly made a monthly period
     * read "2026/12/01 - 2027/01/01"; the last paid day is 31 December.
     */
    private function period(Carbon $start, Carbon $end): string
    {
        return $start->format('Y/m/d').' - '.$end->copy()->subDay()->format('Y/m/d');
    }

    private function yearsLabel(int $years, ?string $locale): string
    {
        return trans_choice('client.invoices.domain_years', $years, ['count' => $years], $locale);
    }

    private function date(mixed $value): ?Carbon
    {
        return $value ? Carbon::parse($value)->startOfDay() : null;
    }
}
