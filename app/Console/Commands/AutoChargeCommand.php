<?php

namespace App\Console\Commands;

use App\Services\AutoChargeService;
use App\Support\AutoCharge;
use Illuminate\Console\Command;

/**
 * Collect the invoices PNLCS has already raised, using the cards customers
 * have already stored.
 *
 * WHEN IT RUNS, AND WHY THAT MINUTE. The billing morning in routes/console.php
 * is a chain, and this has exactly one place in it:
 *
 *   06:00  pnlcs:generate-invoices   the renewals for today exist from here on
 *   06:30  pnlcs:mark-overdue        statuses settle
 *   06:45  THIS
 *   07:00  pnlcs:auto-suspend        customers behind on payment are switched off
 *   07:30  pnlcs:apply-late-fees     overdue invoices grow
 *   08:00  pnlcs:payment-reminders   customers are emailed about what they owe
 *
 * After 06:00 because an invoice has to exist before it can be paid, and a
 * customer whose card works should not wait a day for the money to be taken.
 *
 * After 06:30 because mark-overdue is what moves unpaid and part-paid invoices
 * past their due date into overdue (InvoiceGenerationService::markOverdueInvoices),
 * and a charger reading a status half the chain has not caught up with would
 * disagree with every job after it.
 *
 * Before 07:00 because that is the one that hurts. A customer whose stored card
 * pays at 06:45 is simply never suspended; run this afterwards and they are
 * suspended, then unsuspended by pnlcs:unsuspend-on-payment within the half
 * hour, with an email each way. Invoice::scopeOverduePastGrace already carries
 * that exact complaint in its docblock about a bug of the same shape, which is
 * evidence enough that this house considers it a defect rather than a detail.
 *
 * Before 07:30 for two reasons. A card that pays this morning is never charged
 * a late fee for an invoice it settled before the fee job looked at it — and
 * more importantly, the amount is inside the gateway's idempotency key, so a
 * fee landing between a charge and its retry turns the retry into a brand new
 * request. Running ahead of the fee keeps that from ever happening inside one
 * morning, and AutoCharge::MINIMUM_RETRY_DAYS keeps attempts a day apart.
 *
 * Before 08:00 so that a customer whose renewal has just been paid is not sent
 * a reminder to pay it; PaymentReminderCommand selects unpaid and overdue
 * invoices, and by then this one is paid.
 *
 * Once a day, like every other job in that chain. Retries are measured in days,
 * invoices come due by the day, and a second run would find nothing but rows it
 * is not allowed to touch yet.
 *
 * WITH ONE EXCEPTION, AND IT IS NOT COLLECTION. `--rescue` runs the same
 * command every fifteen minutes over abandoned attempts only: no candidate
 * query, no new invoices, nothing that was not already sent to a gateway by a
 * run that died. It exists because the recovery the attempt row is built for
 * has a deadline — Stripe holds an idempotency key for at least twenty-four
 * hours and the provable-replay window is therefore twenty-three — and a job
 * that runs once a day meets its own wreckage twenty-four hours later, an hour
 * after the only thing that could rescue it has expired. Every interrupted
 * charge used to go to needs_review for that arithmetic reason alone.
 * AutoChargeService::rescue() has the rest of it.
 */
class AutoChargeCommand extends Command
{
    protected $signature = 'pnlcs:auto-charge
        {--limit= : How many cards this run may present (default 1000)}
        {--dry-run : List what would be charged without claiming or charging anything}
        {--rescue : Finish attempts a killed run abandoned; collect no new invoices}';

    protected $description = 'Charge stored cards for invoices that are due';

    public function handle(AutoChargeService $charger): int
    {
        // Off unless an operator has said otherwise, and off means off: this
        // returns before a single invoice is looked at, so an installation that
        // has never heard of the feature runs exactly the queries it ran
        // yesterday. The service asks the same question again for itself —
        // anything that calls it directly gets the same answer — but the
        // command is the thing on the schedule, so the cheapest refusal belongs
        // here.
        if (! AutoCharge::enabled()) {
            $this->info('Automatic card charging is switched off.');

            return Command::SUCCESS;
        }

        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $dryRun = (bool) $this->option('dry-run');
        $rescue = (bool) $this->option('rescue');

        $summary = $charger->run($limit, $dryRun, $rescue);

        if ($dryRun) {
            foreach ($summary['would_charge'] as $row) {
                $this->line("would charge {$row['currency']} ".number_format($row['amount'], 2)." for invoice #{$row['invoice']} on card #{$row['method']}");
            }

            $this->info('Would charge '.count($summary['would_charge']).' invoice(s). Nothing was claimed and nothing was charged.');

            return Command::SUCCESS;
        }

        $this->info(sprintf(
            'Considered %d, charged %d (%s collected), %d awaiting the cardholder, %d refused.',
            $summary['considered'],
            $summary['charged'],
            number_format($summary['collected'], 2),
            $summary['action_required'],
            $summary['failed'],
        ));

        // The quiet outcomes, said out loud. An operator asking why an invoice
        // was not charged this morning deserves the real reason rather than a
        // count that does not add up: held by another worker, not due for a
        // retry yet, finished with, closed because somebody else had already
        // paid it.
        if ($summary['held'] || $summary['not_due'] || $summary['closed'] || $summary['reconciled'] || $summary['waiting']) {
            $this->line(sprintf(
                'Left alone: %d held by another run, %d not due for a retry this morning, %d already finished with, %d closed against a payment recorded elsewhere, %d waiting for a retry date.',
                $summary['held'],
                // Counted, and used to decide whether this line is printed at
                // all, and then not printed: a run whose only event was a
                // NotDue claim said 'Left alone: 0 held, 0 already finished
                // with...' and never mentioned the invoice it had skipped.
                $summary['not_due'],
                $summary['closed'],
                $summary['reconciled'],
                $summary['waiting'],
            ));
        }

        if ($summary['recovered']) {
            $this->line(sprintf('Recovered: %d payment(s) that had been taken but never recorded were credited.', $summary['recovered']));
        }

        if ($summary['errors']) {
            $this->warn(sprintf('%d invoice(s) threw while being collected and were stepped over. See the log.', $summary['errors']));
        }

        // The ones that mean somebody has to do something, each on its own line
        // and deliberately NOT folded into the counts above — the first review
        // of this feature found needs_review reported inside the same number as
        // invoices that had been paid, which is how an operator learns nothing
        // at all.
        //
        // This used to say the lines went 'on stderr rather than stdout: cron
        // mails stderr', and neither half was true. Command::error() writes to
        // the ordinary output stream with an <error> style
        // (InteractsWithIO::line), and on the schedule neither stream reaches
        // anybody: Event::$output defaults to /dev/null and CommandBuilder
        // appends 2>&1. This is the channel for an operator running the command
        // by hand. The one that reaches a person unattended is the error log,
        // the email to SystemEmailAddress, the notification event and the
        // dashboard count, all raised by the service itself.
        if ($summary['outcome_unknown']) {
            $this->error(sprintf(
                '%d charge(s) were sent and no answer came back. Nothing is recorded against invoice(s) %s and the money may have moved; '
                .'the rescue sweep will repeat the identical request inside the gateway\'s idempotency window to find out, and will fetch '
                .'a person if it cannot.',
                $summary['outcome_unknown'],
                implode(', ', array_map(static fn ($id) => '#'.$id, $summary['unknown_invoices'])),
            ));
        }

        if ($summary['taken_not_recorded']) {
            $this->error(sprintf(
                '%d card(s) were charged and the payment could not be recorded. The gateway reference is on the attempt row and the next rescue sweep will credit it.',
                $summary['taken_not_recorded'],
            ));
        }

        if ($summary['needs_review']) {
            $this->error(sprintf(
                '%d invoice(s) NEED A PERSON: a charge was sent and its outcome could not be established, so money may have moved. Check the gateway for invoice(s) %s. Nothing automatic will touch them again.',
                $summary['needs_review'],
                implode(', ', array_map(static fn ($id) => '#'.$id, $summary['review_invoices'])),
            ));
        }

        foreach ($summary['skipped'] as $reason => $count) {
            $this->line("skipped {$count}: {$reason}");
        }

        return Command::SUCCESS;
    }
}
