<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Ticket;
use App\Models\TicketSpamFilter;
use Illuminate\Support\Facades\Log;

class TicketSpamService
{
    public function isSpam(string $email, string $subject, string $message): bool
    {
        // Check banned email patterns from ticket_spam_filters
        $emailFilters = TicketSpamFilter::where("type", "email")->pluck("content")->toArray();
        foreach ($emailFilters as $pattern) {
            $pattern = trim($pattern);
            if (!$pattern) {
                continue;
            }
            if (str_contains(strtolower($email), strtolower($pattern))) {
                Log::info("Ticket spam filter: blocked email matching pattern {}");
                return true;
            }
        }

        // Check banned keywords in subject/message
        $keywordFilters = TicketSpamFilter::where("type", "keyword")->pluck("content")->toArray();
        $content = strtolower($subject . " " . $message);
        foreach ($keywordFilters as $keyword) {
            $keyword = trim(strtolower($keyword));
            if (!$keyword) {
                continue;
            }
            if (str_contains($content, $keyword)) {
                Log::info("Ticket spam filter: blocked content matching keyword {}");
                return true;
            }
        }

        // Rate limit: max tickets per hour per email
        $maxPerHour = (int) Setting::get("TicketSpamMaxPerHour", 5);
        if ($maxPerHour > 0) {
            $recentCount = Ticket::where("email", $email)
                ->where("created_at", ">=", now()->subHour())
                ->count();
            if ($recentCount >= $maxPerHour) {
                Log::info("Ticket spam filter: rate limit exceeded for {} ({$recentCount}/{$maxPerHour})");
                return true;
            }
        }

        return false;
    }
}
