<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ViewServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        View::composer('admin.layouts.app', function ($view) {
            // Single query to get all sidebar counts
            $counts = DB::selectOne("
                SELECT
                    (SELECT COUNT(*) FROM orders WHERE status = 'pending') as pending_orders,
                    (SELECT COUNT(*) FROM invoices WHERE status = 'Unpaid') as unpaid_invoices,
                    (SELECT COUNT(*) FROM invoices WHERE status = 'Overdue') as overdue_invoices,
                    (SELECT COUNT(*) FROM tickets WHERE status IN ('Open','Customer-Reply')) as open_tickets,
                    (SELECT COUNT(*) FROM tickets WHERE status = 'Open') as open_tickets_only,
                    (SELECT COUNT(*) FROM tickets WHERE status = 'Customer-Reply') as awaiting_tickets,
                    (SELECT COUNT(*) FROM tickets WHERE status NOT IN ('Closed')) as active_tickets,
                    (SELECT COUNT(*) FROM tickets WHERE priority = 'High' AND status NOT IN ('Closed')) as high_priority_tickets
            ");

            $view->with('sidebarCounts', $counts ?: (object)[
                'pending_orders' => 0, 'unpaid_invoices' => 0, 'overdue_invoices' => 0,
                'open_tickets' => 0, 'open_tickets_only' => 0, 'awaiting_tickets' => 0,
                'active_tickets' => 0, 'high_priority_tickets' => 0,
            ]);
        });
    }
}
