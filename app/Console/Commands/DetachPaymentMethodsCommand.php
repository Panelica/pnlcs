<?php

namespace App\Console\Commands;

use App\Contracts\TokenizableGatewayInterface;
use App\Models\PaymentMethod;
use App\Services\Module\ModuleRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Finish removing the cards customers have removed.
 *
 * Deleting the row stops PNLCS charging a card. It does not stop the gateway
 * holding it, and a customer who clicks Remove is asking for both. The gateway
 * call cannot live in that click — a third party having a slow morning would
 * hang a customer's own page, and a failure there would leave a deleted row
 * over a live token with nothing that would ever try again — so the click
 * records the request and this carries it out.
 *
 * WHY A SCHEDULED SWEEP AND NOT A QUEUED JOB. A job gets three attempts and
 * then lands in failed_jobs, which is a table nobody in this product reads:
 * the card would stay at the gateway for ever and the only trace would be a
 * row in a table with no screen. A sweep has no attempt limit, picks up
 * anything left behind by a gateway outage however long it lasted, needs no
 * worker of its own beyond the one the scheduler already runs, and reports
 * what it cannot finish. The cost of being wrong the other way is one indexed
 * SELECT every five minutes that usually matches nothing.
 *
 * IT DOES NOT ASK WHETHER THE FEATURE IS SWITCHED ON, and that is deliberate.
 * An operator who turns automatic charging off does not thereby withdraw a
 * customer's request to have their card removed; the outstanding requests still
 * have to be finished. It costs nothing on an installation that has never
 * stored a card, because only a row with a gateway token is ever waiting, and
 * only the vaulting flow writes one.
 */
class DetachPaymentMethodsCommand extends Command
{
    protected $signature = 'pnlcs:detach-payment-methods';

    protected $description = 'Detach removed payment methods at the gateway that still holds them';

    /**
     * How long a request may go unfinished before the operator hears about it.
     *
     * Long enough that a gateway outage, a rate limit or an hour with the wrong
     * API key in the settings resolves itself quietly; short enough that a card
     * a customer asked us to stop keeping does not sit there for a week.
     */
    private const SHOUT_AFTER_HOURS = 24;

    public function handle(ModuleRegistry $modules): int
    {
        $pending = PaymentMethod::query()->awaitingGatewayDetach()->orderBy('detach_requested_at')->get();

        if ($pending->isEmpty()) {
            return Command::SUCCESS;
        }

        $gateways = $modules->tokenisedGateways();
        $detached = 0;
        $waiting = 0;
        $unresolvable = 0;
        $stuck = [];

        foreach ($pending as $method) {
            // NOTHING HERE CAN EVER DETACH THIS ONE, so it is not asked about.
            //
            // The two reasons a module is missing from the list above are not
            // the same fact and must not be treated alike. A gateway that has
            // been switched off, or whose keys were rotated, CAN detach and
            // will be able to again — that row keeps waiting, and after a day
            // somebody is told. A gateway whose module does not implement the
            // interface at all never will, whatever an operator does: asking
            // again in five minutes, and in five minutes after that, produces
            // an error log every tick for the life of the installation and
            // moves nothing.
            //
            // Rows like that should no longer be created at all
            // (PaymentMethod::requestGatewayDetach refuses to record one, and
            // says so once where the customer can be named), so this is for the
            // ones already on disk. It writes nothing: detached_at would claim
            // the gateway has let the card go when it has not, and clearing the
            // request would erase the customer having asked. The row stays
            // exactly as it is, honest and outstanding, and stops being shouted
            // about by a sweep that has no answer for it. It is counted so that
            // it is not invisible either.
            if (! $modules->canDetachStoredMethods((string) $method->gateway_name)) {
                $unresolvable++;

                continue;
            }

            $module = $gateways[strtolower((string) $method->gateway_name)] ?? null;

            if (! $module instanceof TokenizableGatewayInterface) {
                // The gateway has been switched off, or has lost the keys it
                // needs. Nothing can be detached until it comes back, and
                // marking the row done would claim the card is gone when it is
                // not. It stays outstanding and is reported below.
                $waiting++;
                $this->noteIfStuck($method, 'its gateway is not currently usable', $stuck);

                continue;
            }

            // ONE BAD ROW MUST NOT STOP THE REST. The realistic thrower is the
            // HTTP client itself, and the row after this one belongs to a
            // different customer who asked for the same thing.
            try {
                $result = $module->detachStoredMethod($method);
            } catch (\Throwable $e) {
                $waiting++;
                Log::error('Detach sweep: a stored card could not be detached and the sweep moved on', [
                    'method' => $method->id,
                    'client' => $method->client_id,
                    'exception' => $e::class,
                    'error' => $e->getMessage(),
                ]);
                $this->noteIfStuck($method, $e->getMessage(), $stuck);

                continue;
            }

            if ($result['success'] ?? false) {
                $method->markGatewayDetached();
                $detached++;

                continue;
            }

            $waiting++;

            // Not retryable means a person has to look, so it is said at once
            // rather than after a day of silence.
            if (($result['retryable'] ?? true) === false) {
                Log::error('Detach sweep: a stored card cannot be detached without somebody looking at it', [
                    'method' => $method->id,
                    'client' => $method->client_id,
                    'gateway' => $method->gateway_name,
                    'error' => $result['message'] ?? null,
                ]);
                $stuck[] = $method->id;

                continue;
            }

            $this->noteIfStuck($method, $result['message'] ?? 'the gateway refused', $stuck);
        }

        $this->info("Detached {$detached} removed payment method(s) at the gateway; {$waiting} still waiting.");

        if ($unresolvable > 0) {
            $this->warn(
                $unresolvable.' removed card(s) are stored with a gateway this installation cannot detach at. '
                .'PNLCS has stopped using them; the stored tokens have to be removed in the gateway\'s own dashboard.'
            );
        }

        if ($stuck !== []) {
            // $this->error(), which is what every other command in PNLCS uses
            // to say something went wrong, and what AutoChargeCommand uses for
            // the two lines it wants a person to read.
            //
            // IT WAS $this->getOutput()->getErrorOutput() AND THAT IS A FATAL.
            // Command::getOutput() returns Illuminate\Console\OutputStyle,
            // which extends Symfony's SymfonyStyle, which extends
            // Symfony\Component\Console\Style\OutputStyle — and
            // getErrorOutput() is declared `protected` there
            // (vendor/symfony/console/Style/OutputStyle.php:106). Calling it
            // from here is an Error, not an exception, so the one branch that
            // exists to fetch a person was the one branch that killed the
            // command: every five minutes, for ever, from the first card a
            // gateway would not let go of, with the alert never sent.
            //
            // The choice of stream is not what made this safe, and the comment
            // that used to be here ('cron mails stderr') was wrong twice over:
            // Command::error() writes to stdout with an <error> style
            // (InteractsWithIO::line), and on the schedule neither stream
            // reaches anybody, because Event::$output defaults to /dev/null and
            // CommandBuilder appends 2>&1. The channel an operator actually has
            // is the error log above, which their aggregator reads. This line
            // is for the operator running the sweep by hand.
            $this->error(
                'PNLCS: '.count($stuck).' removed card(s) are still stored at the gateway and need a person: #'
                .implode(', #', $stuck)
            );
        }

        return Command::SUCCESS;
    }

    /**
     * Say it out loud once the request has been outstanding too long.
     *
     * Not on the first failure: gateways have bad minutes, and an operator who
     * is emailed about every one of them stops reading the emails.
     */
    private function noteIfStuck(PaymentMethod $method, string $reason, array &$stuck): void
    {
        if ($method->detach_requested_at === null
            || $method->detach_requested_at->gt(now()->subHours(self::SHOUT_AFTER_HOURS))) {
            return;
        }

        Log::error('Detach sweep: a card a customer removed is still stored at the gateway', [
            'method' => $method->id,
            'client' => $method->client_id,
            'gateway' => $method->gateway_name,
            'requested_at' => $method->detach_requested_at->toDateTimeString(),
            'reason' => $reason,
        ]);

        $stuck[] = $method->id;
    }
}
