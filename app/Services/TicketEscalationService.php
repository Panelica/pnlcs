<?php

namespace App\Services;

use App\Events\TicketReplied;
use App\Models\Ticket;
use App\Models\TicketEscalation;
use Illuminate\Support\Facades\Log;

class TicketEscalationService
{
    /**
     * Check all active escalation rules and escalate matching tickets.
     */
    public function checkAndEscalate(): int
    {
        $rules = TicketEscalation::all();
        $escalated = 0;

        foreach ($rules as $rule) {
            if ($rule->time_elapsed <= 0) {
                continue;
            }

            $threshold = now()->subMinutes($rule->time_elapsed);

            $query = Ticket::where('last_reply', '<=', $threshold);

            // Filter by statuses (JSON array or fallback to open+customer-reply)
            $statuses = $rule->statuses;
            if (! empty($statuses) && is_array($statuses)) {
                $query->whereIn('status', $statuses);
            } else {
                $query->whereIn('status', ['open', 'customer-reply']);
            }

            // Filter by departments
            $departments = $rule->departments;
            if (! empty($departments) && is_array($departments)) {
                $query->whereIn('department_id', $departments);
            }

            // Filter by priorities
            $priorities = $rule->priorities;
            if (! empty($priorities) && is_array($priorities)) {
                $query->whereIn('priority', $priorities);
            }

            // Skip tickets already escalated since the last reply.
            $query->whereNull('escalated_at');

            $tickets = $query->get();

            foreach ($tickets as $ticket) {
                $changes = [];

                if ($rule->flag_to) {
                    $ticket->admin = $rule->flag_to;
                    // flag is the assigned-admin id the support widget counts.
                    $ticket->flag = (int) $rule->flag_to;
                    $changes[] = "assigned to admin #{$rule->flag_to}";
                }

                // Record the escalation on its own column. It used to be written
                // into flag as the string "escalated", which that integer column
                // rejected, so a rule that only sent an auto-reply re-fired every
                // cycle and mailed the customer the same message forever.
                $ticket->escalated_at = now();

                if ($rule->new_priority) {
                    $ticket->priority = $rule->new_priority;
                    $changes[] = "priority changed to {$rule->new_priority}";
                }

                if ($rule->new_department_id) {
                    $ticket->department_id = $rule->new_department_id;
                    $changes[] = "moved to department #{$rule->new_department_id}";
                }

                $ticket->save();

                // Add a note about the escalation
                $noteMessage = "Auto-escalated by rule \"{$rule->name}\": No response for {$rule->time_elapsed} minutes.";
                if (! empty($changes)) {
                    $noteMessage .= ' Actions: '.implode(', ', $changes).'.';
                }

                $ticket->notes()->create([
                    'message' => $noteMessage,
                    'admin' => 'System',
                ]);

                // Add auto-reply if configured
                if (! empty($rule->add_reply)) {
                    $ticket->replies()->create([
                        'message' => $rule->add_reply,
                        'admin' => 'System',
                        'name' => 'System',
                        'email' => 'system@localhost',
                    ]);
                    $ticket->update(['last_reply' => now()]);

                    // r123-autoreply: send it. The whole point of a reply on an
                    // escalation rule is that a customer who has been waiting
                    // long enough to trigger one hears something. This wrote it
                    // into the ticket and raised nothing, so the message sat in
                    // the panel and the customer heard nothing at all - every
                    // other reply, from staff or from the API, goes out through
                    // this event. The escalated_at stamp above is what keeps it
                    // to one message per silence.
                    event(new TicketReplied($ticket->fresh(), (string) $rule->add_reply, true));
                }

                $escalated++;
                Log::info("Ticket #{$ticket->id} escalated by rule \"{$rule->name}\"");
            }
        }

        return $escalated;
    }
}
