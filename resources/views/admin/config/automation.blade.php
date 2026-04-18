@extends("admin.layouts.app")
@section("title", __("admin.automation_status"))
@section("content")

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>{{ __('admin.automation.title') }}</h1>
</div>

<div class="card">
    <div class="card-header"><strong>{{ __('admin.automation.cron_job_status') }}</strong></div>
    <div class="card-body">
        <table class="data-table">
            <thead>
                <tr><th>{{ __('admin.automation.task') }}</th><th>{{ __('admin.automation.frequency') }}</th><th>{{ __('admin.automation.last_run') }}</th><th>{{ __('common.table.status') }}</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>{{ __('admin.automation.invoice_generation') }}</strong><br><small style="color:#777;">{{ __('admin.automation.invoice_generation_desc') }}</small></td>
                    <td>{{ __('admin.automation.daily') }}</td>
                    <td style="color:#999;">{{ __('admin.automation.never') }}</td>
                    <td><span class="badge-suspended">{{ __('admin.automation.not_configured') }}</span></td>
                </tr>
                <tr>
                    <td><strong>{{ __('admin.automation.invoice_reminders') }}</strong><br><small style="color:#777;">{{ __('admin.automation.invoice_reminders_desc') }}</small></td>
                    <td>{{ __('admin.automation.daily') }}</td>
                    <td style="color:#999;">{{ __('admin.automation.never') }}</td>
                    <td><span class="badge-suspended">{{ __('admin.automation.not_configured') }}</span></td>
                </tr>
                <tr>
                    <td><strong>{{ __('admin.automation.service_suspension') }}</strong><br><small style="color:#777;">{{ __('admin.automation.service_suspension_desc') }}</small></td>
                    <td>{{ __('admin.automation.daily') }}</td>
                    <td style="color:#999;">{{ __('admin.automation.never') }}</td>
                    <td><span class="badge-suspended">{{ __('admin.automation.not_configured') }}</span></td>
                </tr>
                <tr>
                    <td><strong>{{ __('admin.automation.service_termination') }}</strong><br><small style="color:#777;">{{ __('admin.automation.service_termination_desc') }}</small></td>
                    <td>{{ __('admin.automation.daily') }}</td>
                    <td style="color:#999;">{{ __('admin.automation.never') }}</td>
                    <td><span class="badge-suspended">{{ __('admin.automation.not_configured') }}</span></td>
                </tr>
                <tr>
                    <td><strong>{{ __('admin.automation.domain_reminders') }}</strong><br><small style="color:#777;">{{ __('admin.automation.domain_reminders_desc') }}</small></td>
                    <td>{{ __('admin.automation.daily') }}</td>
                    <td style="color:#999;">{{ __('admin.automation.never') }}</td>
                    <td><span class="badge-suspended">{{ __('admin.automation.not_configured') }}</span></td>
                </tr>
                <tr>
                    <td><strong>{{ __('admin.automation.cc_expiry') }}</strong><br><small style="color:#777;">{{ __('admin.automation.cc_expiry_desc') }}</small></td>
                    <td>{{ __('admin.automation.monthly') }}</td>
                    <td style="color:#999;">{{ __('admin.automation.never') }}</td>
                    <td><span class="badge-suspended">{{ __('admin.automation.not_configured') }}</span></td>
                </tr>
                <tr>
                    <td><strong>{{ __('admin.automation.ticket_autoclose') }}</strong><br><small style="color:#777;">{{ __('admin.automation.ticket_autoclose_desc') }}</small></td>
                    <td>{{ __('admin.automation.daily') }}</td>
                    <td style="color:#999;">{{ __('admin.automation.never') }}</td>
                    <td><span class="badge-suspended">{{ __('admin.automation.not_configured') }}</span></td>
                </tr>
                <tr>
                    <td><strong>{{ __('admin.automation.affiliate_reports') }}</strong><br><small style="color:#777;">{{ __('admin.automation.affiliate_reports_desc') }}</small></td>
                    <td>{{ __('admin.automation.monthly') }}</td>
                    <td style="color:#999;">{{ __('admin.automation.never') }}</td>
                    <td><span class="badge-suspended">{{ __('admin.automation.not_configured') }}</span></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="card" style="margin-top:15px;">
    <div class="card-header"><strong>{{ __('admin.automation.cron_configuration') }}</strong></div>
    <div class="card-body">
        <p style="font-size:13px;color:#555;">{{ __('admin.automation.cron_instruction') }}</p>
        <div style="background:#f5f5f5;padding:12px;border-radius:4px;font-family:monospace;font-size:12px;color:#333;margin:10px 0;">
            */5 * * * * cd {{ base_path() }} && php artisan schedule:run >> /dev/null 2>&1
        </div>
        <p style="font-size:12px;color:#999;margin-top:8px;">{{ __('admin.automation.cron_explanation') }}</p>
    </div>
</div>

@endsection
