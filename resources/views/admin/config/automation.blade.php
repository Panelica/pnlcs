@extends("admin.layouts.app")
@section("title", "Automation Status")
@section("content")

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>Automation Status</h1>
</div>

<div class="card">
    <div class="card-header"><strong>Cron Job Status</strong></div>
    <div class="card-body">
        <table class="data-table">
            <thead>
                <tr><th>Task</th><th>Frequency</th><th>Last Run</th><th>Status</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Invoice Generation</strong><br><small style="color:#777;">Auto-generate invoices for upcoming renewals</small></td>
                    <td>Daily</td>
                    <td style="color:#999;">Never</td>
                    <td><span class="badge-suspended">Not Configured</span></td>
                </tr>
                <tr>
                    <td><strong>Invoice Reminders</strong><br><small style="color:#777;">Send overdue invoice reminder emails</small></td>
                    <td>Daily</td>
                    <td style="color:#999;">Never</td>
                    <td><span class="badge-suspended">Not Configured</span></td>
                </tr>
                <tr>
                    <td><strong>Service Suspension</strong><br><small style="color:#777;">Auto-suspend services with overdue invoices</small></td>
                    <td>Daily</td>
                    <td style="color:#999;">Never</td>
                    <td><span class="badge-suspended">Not Configured</span></td>
                </tr>
                <tr>
                    <td><strong>Service Termination</strong><br><small style="color:#777;">Auto-terminate suspended services after grace period</small></td>
                    <td>Daily</td>
                    <td style="color:#999;">Never</td>
                    <td><span class="badge-suspended">Not Configured</span></td>
                </tr>
                <tr>
                    <td><strong>Domain Renewal Reminders</strong><br><small style="color:#777;">Send domain expiry reminders to clients</small></td>
                    <td>Daily</td>
                    <td style="color:#999;">Never</td>
                    <td><span class="badge-suspended">Not Configured</span></td>
                </tr>
                <tr>
                    <td><strong>Credit Card Expiry Notices</strong><br><small style="color:#777;">Notify clients about expiring cards</small></td>
                    <td>Monthly</td>
                    <td style="color:#999;">Never</td>
                    <td><span class="badge-suspended">Not Configured</span></td>
                </tr>
                <tr>
                    <td><strong>Ticket Auto-Close</strong><br><small style="color:#777;">Close inactive tickets after configured period</small></td>
                    <td>Daily</td>
                    <td style="color:#999;">Never</td>
                    <td><span class="badge-suspended">Not Configured</span></td>
                </tr>
                <tr>
                    <td><strong>Affiliate Reports</strong><br><small style="color:#777;">Calculate affiliate commissions</small></td>
                    <td>Monthly</td>
                    <td style="color:#999;">Never</td>
                    <td><span class="badge-suspended">Not Configured</span></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="card" style="margin-top:15px;">
    <div class="card-header"><strong>Cron Configuration</strong></div>
    <div class="card-body">
        <p style="font-size:13px;color:#555;">Add the following cron job to enable automation:</p>
        <div style="background:#f5f5f5;padding:12px;border-radius:4px;font-family:monospace;font-size:12px;color:#333;margin:10px 0;">
            */5 * * * * cd {{ base_path() }} && php artisan schedule:run >> /dev/null 2>&1
        </div>
        <p style="font-size:12px;color:#999;margin-top:8px;">The cron command should be set up on your server to run every 5 minutes. Laravel scheduler will then handle the individual task frequencies.</p>
    </div>
</div>

@endsection
