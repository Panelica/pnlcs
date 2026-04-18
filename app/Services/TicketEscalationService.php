<?php

namespace App\Services;

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
            if ($rule->time_elapsed <= 0) continue;

            $threshold = now()->subMinutes($rule->time_elapsed);

            $query = Ticket::where("last_reply", "<=", $threshold);

            // Filter by statuses (JSON array or fallback to open+customer-reply)
            $statuses = $rule->statuses;
            if (!empty($statuses) && is_array($statuses)) {
                $query->whereIn("status", $statuses);
            } else {
                $query->whereIn("status", ["open", "customer-reply"]);
            }

            // Filter by departments
            $departments = $rule->departments;
            if (!empty($departments) && is_array($departments)) {
                $query->whereIn("department_id", $departments);
            }

            // Filter by priorities
            $priorities = $rule->priorities;
            if (!empty($priorities) && is_array($priorities)) {
                $query->whereIn("priority", $priorities);
            }

            // Skip already-escalated tickets (flagged as escalated)
            $query->where(function ($q) {
                $q->whereNull("flag")->orWhere("flag", "!=", "escalated");
            });

            $tickets = $query->get();

            foreach ($tickets as $ticket) {
                $changes = [];

                if ($rule->flag_to) {
                    $ticket->admin = $rule->flag_to;
                    $ticket->flag = "escalated";
                    $changes[] = "assigned to admin #{$rule->flag_to}";
                }

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
                if (!empty($changes)) {
                    $noteMessage .= " Actions: " . implode(", ", $changes) . ".";
                }

                $ticket->notes()->create([
                    "message" => $noteMessage,
                    "admin" => "System",
                ]);

                // Add auto-reply if configured
                if (!empty($rule->add_reply)) {
                    $ticket->replies()->create([
                        "message" => $rule->add_reply,
                        "admin" => "System",
                        "name" => "System",
                        "email" => "system@localhost",
                    ]);
                    $ticket->update(["last_reply" => now()]);
                }

                $escalated++;
                Log::info("Ticket #{$ticket->id} escalated by rule \"{$rule->name}\"");
            }
        }

        return $escalated;
    }
}
