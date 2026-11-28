<?php

namespace Modules\Addons\BankPayments\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The bank-specific clock.
 *
 * Nest Bank posts incoming Elixir transfers at configured sessions. The addon
 * waits booking_delay_minutes after each session, then allows sync/booking in a
 * short grace window. Operators see all times in the panel's display timezone.
 */
class BankBookingSchedule
{
    public function isBusinessDay(string $bank, CarbonInterface $now): bool
    {
        $bankConfig = $this->bankConfig($bank);
        $local = $this->local($now);

        if (($bankConfig['weekdays_only'] ?? true) && $local->isWeekend()) {
            return false;
        }

        $holidays = array_filter((array) ($bankConfig['holidays'] ?? []));

        return ! in_array($local->toDateString(), $holidays, true);
    }

    public function isSyncWindowOpen(string $bank, ?CarbonInterface $now = null): bool
    {
        return $this->isBookingWindowOpen($bank, $now);
    }

    public function isBookingWindowOpen(string $bank, ?CarbonInterface $now = null): bool
    {
        $now = $now !== null ? $this->local($now) : $this->local(CarbonImmutable::now());

        if (! $this->isBusinessDay($bank, $now)) {
            return false;
        }

        foreach ($this->windowBounds($bank, $now) as [$start, $end]) {
            if ($now->betweenIncluded($start, $end)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The next effective window start, for display/status purposes.
     */
    public function nextWindow(string $bank, ?CarbonInterface $now = null): ?CarbonImmutable
    {
        $now = $now !== null ? $this->local($now) : $this->local(CarbonImmutable::now());
        $bankConfig = $this->bankConfig($bank);

        for ($day = 0; $day < 8; $day++) {
            $date = $now->startOfDay()->addDays($day);

            if (! $this->isBusinessDay($bank, $date)) {
                continue;
            }

            foreach ($this->windowBounds($bank, $date) as [$start]) {
                if ($day > 0 || $start->greaterThan($now)) {
                    return $start;
                }
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function bankConfig(string $bank): array
    {
        $config = config('bank-payments.banks.'.$bank, []);

        return is_array($config) ? $config : [];
    }

    private function local(CarbonInterface $at): CarbonImmutable
    {
        return CarbonImmutable::instance($at)->setTimezone(display_tz());
    }

    /**
     * @return array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function windowBounds(string $bank, CarbonInterface $date): array
    {
        $bankConfig = $this->bankConfig($bank);
        $delay = max(0, (int) ($bankConfig['booking_delay_minutes'] ?? 30));
        $grace = max(1, (int) ($bankConfig['booking_grace_minutes'] ?? 10));
        $bounds = [];

        foreach ((array) ($bankConfig['sessions'] ?? []) as $session) {
            if (! is_string($session) || ! preg_match('/^\d{2}:\d{2}$/', $session)) {
                continue;
            }

            $start = CarbonImmutable::parse($date->toDateString().' '.$session, display_tz());

            if ($start === false) {
                continue;
            }

            $start = $start->addMinutes($delay);
            $bounds[] = [$start, $start->addMinutes($grace)];
        }

        return $bounds;
    }
}
