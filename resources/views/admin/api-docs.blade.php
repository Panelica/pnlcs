@extends('admin.layouts.app')
@section('title', __('admin.api_docs.title'))
@section('content')

<style>
.api-section { margin-bottom: 32px; }
.api-section h3 { font-size: 16px; font-weight: 700; padding: 8px 12px; background: #f5f5f5; border-left: 4px solid #337ab7; margin: 0 0 0 0; border-radius: 2px; }
.api-table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 0; }
.api-table th { background: #fafafa; padding: 8px 10px; text-align: left; border-bottom: 2px solid #e5e5e5; font-weight: 600; color: #555; }
.api-table td { padding: 7px 10px; border-bottom: 1px solid #f0f0f0; vertical-align: top; }
.api-table tr:hover { background: #fafcff; }
.badge-get { background: #5cb85c; color: #fff; padding: 1px 6px; border-radius: 3px; font-size: 11px; font-weight: 700; }
.badge-post { background: #337ab7; color: #fff; padding: 1px 6px; border-radius: 3px; font-size: 11px; font-weight: 700; }
.ep-url { font-family: monospace; font-size: 12px; color: #333; }
.ep-desc { color: #555; }
.code-block { background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 12px; overflow-x: auto; margin: 0; }
.tab-btns { display: flex; gap: 4px; margin-bottom: 8px; }
.tab-btn { padding: 4px 12px; border: 1px solid #ddd; background: #f5f5f5; cursor: pointer; border-radius: 3px; font-size: 12px; }
.tab-btn.active { background: #337ab7; color: #fff; border-color: #337ab7; }
.tab-pane { display: none; }
.tab-pane.active { display: block; }
.toc-list { column-count: 3; column-gap: 20px; padding: 0; list-style: none; margin: 0; }
.toc-list li { margin-bottom: 4px; }
.toc-list a { color: #337ab7; font-size: 13px; text-decoration: none; }
.toc-list a:hover { text-decoration: underline; }
@media (max-width: 900px) { .toc-list { column-count: 2; } }
@media (max-width: 600px) { .toc-list { column-count: 1; } }
</style>

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>{{ __('admin.api_docs.title') }}</h1>
    <a href="{{ route('admin.config.api-credentials') }}" class="btn btn-default btn-sm">{{ __('admin.api_docs.manage_keys') }}</a>
</div>

{{-- Authentication --}}
<div class="card" style="margin-bottom:24px;">
    <div class="card-header" style="background:#f5f5f5;padding:12px 16px;border-bottom:1px solid #e5e5e5;">
        <h2 style="margin:0;font-size:16px;font-weight:700;">{{ __('admin.api_docs.auth_title') }}</h2>
    </div>
    <div class="card-body" style="padding:16px;">
        <p style="margin:0 0 12px;font-size:13px;color:#555;">{!! __('admin.api_docs.auth_intro') !!}</p>
        <table style="width:100%;border-collapse:collapse;font-size:13px;margin-bottom:16px;">
            <thead><tr><th style="text-align:left;padding:6px 10px;background:#fafafa;border-bottom:2px solid #e5e5e5;">{{ __('admin.api_docs.parameter') }}</th><th style="text-align:left;padding:6px 10px;background:#fafafa;border-bottom:2px solid #e5e5e5;">{{ __('common.table.description') }}</th></tr></thead>
            <tbody>
                <tr><td style="padding:6px 10px;border-bottom:1px solid #f0f0f0;"><code>identifier</code></td><td style="padding:6px 10px;border-bottom:1px solid #f0f0f0;">{{ __('admin.api_docs.identifier_desc') }}</td></tr>
                <tr><td style="padding:6px 10px;border-bottom:1px solid #f0f0f0;"><code>secret</code></td><td style="padding:6px 10px;border-bottom:1px solid #f0f0f0;">{{ __('admin.api_docs.secret_desc') }}</td></tr>
                <tr><td style="padding:6px 10px;"><code>action</code></td><td style="padding:6px 10px;">{!! __('admin.api_docs.action_param_desc') !!}</td></tr>
            </tbody>
        </table>
        <p style="font-size:13px;color:#555;margin:0 0 8px;"><strong>{{ __('admin.api_docs.base_url') }}:</strong> <code>POST {{ url('/api/v1') }}</code></p>
        <p style="font-size:12px;color:#888;margin:0;">{!! __('admin.api_docs.response_note') !!}</p>
    </div>
</div>

{{-- Table of Contents --}}
<div class="card" style="margin-bottom:24px;">
    <div class="card-header" style="background:#f5f5f5;padding:12px 16px;border-bottom:1px solid #e5e5e5;">
        <h2 style="margin:0;font-size:16px;font-weight:700;">{{ __('admin.api_docs.toc') }}</h2>
    </div>
    <div class="card-body" style="padding:16px;">
        <ul class="toc-list">
            <li><a href="#section-system">{{ __('admin.api_docs.section_system') }}</a></li>
            <li><a href="#section-clients">{{ __('admin.api_docs.section_clients') }}</a></li>
            <li><a href="#section-invoices">{{ __('admin.api_docs.section_invoices') }}</a></li>
            <li><a href="#section-orders">{{ __('admin.api_docs.section_orders') }}</a></li>
            <li><a href="#section-tickets">{{ __('admin.api_docs.section_tickets') }}</a></li>
            <li><a href="#section-domains">{{ __('admin.api_docs.section_domains') }}</a></li>
            <li><a href="#section-services">{{ __('admin.api_docs.section_services') }}</a></li>
            <li><a href="#section-quotes">{{ __('admin.api_docs.section_quotes') }}</a></li>
            <li><a href="#section-projects">{{ __('admin.api_docs.section_projects') }}</a></li>
            <li><a href="#section-billing">{{ __('admin.api_docs.section_billing') }}</a></li>
            <li><a href="#section-products">{{ __('admin.api_docs.section_products') }}</a></li>
            <li><a href="#section-emails">{{ __('admin.api_docs.section_emails') }}</a></li>
            <li><a href="#section-admins">{{ __('admin.api_docs.section_admins') }}</a></li>
            <li><a href="#section-examples">{{ __('admin.api_docs.section_examples') }}</a></li>
        </ul>
    </div>
</div>

{{-- SYSTEM --}}
<div class="card api-section" id="section-system">
    <h3>{{ __('admin.api_docs.section_system') }}</h3>
    <table class="api-table">
        <thead><tr><th style="width:70px;">{{ __('admin.api_docs.method') }}</th><th style="width:220px;">{{ __('admin.api_docs.action') }}</th><th>{{ __('common.table.description') }}</th><th style="width:220px;">{{ __('admin.api_docs.key_params') }}</th></tr></thead>
        <tbody>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getstats</td><td class="ep-desc">{{ __('admin.api_docs.desc_getstats') }}</td><td></td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">gethealthstatus</td><td class="ep-desc">{{ __('admin.api_docs.desc_gethealthstatus') }}</td><td></td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">pnlcsdetails</td><td class="ep-desc">{{ __('admin.api_docs.desc_pnlcsdetails') }}</td><td></td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">sendadminemail</td><td class="ep-desc">{{ __('admin.api_docs.desc_sendadminemail') }}</td><td>subject, message</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">addannouncement</td><td class="ep-desc">{{ __('admin.api_docs.desc_addannouncement') }}</td><td>title, announcement, published</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getannouncements</td><td class="ep-desc">{{ __('admin.api_docs.desc_getannouncements') }}</td><td>limitnum, limitstart</td></tr>
        </tbody>
    </table>
</div>

{{-- CLIENTS --}}
<div class="card api-section" id="section-clients">
    <h3>{{ __('admin.api_docs.section_clients') }}</h3>
    <table class="api-table">
        <thead><tr><th style="width:70px;">{{ __('admin.api_docs.method') }}</th><th style="width:220px;">{{ __('admin.api_docs.action') }}</th><th>{{ __('common.table.description') }}</th><th style="width:220px;">{{ __('admin.api_docs.key_params') }}</th></tr></thead>
        <tbody>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getclients</td><td class="ep-desc">{{ __('admin.api_docs.desc_getclients') }}</td><td>search, limitnum, limitstart, sorting, orderby</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getclientsdetails</td><td class="ep-desc">{{ __('admin.api_docs.desc_getclientsdetails') }}</td><td>clientid <em>or</em> email (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">addclient</td><td class="ep-desc">{{ __('admin.api_docs.desc_addclient') }}</td><td>firstname, lastname, email, password, address1, city, country, phonenumber</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">updateclient</td><td class="ep-desc">{{ __('admin.api_docs.desc_updateclient') }}</td><td>clientid (required), any client field</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">deleteclient</td><td class="ep-desc">{{ __('admin.api_docs.desc_deleteclient') }}</td><td>clientid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getclientpassword</td><td class="ep-desc">{{ __('admin.api_docs.desc_getclientpassword') }}</td><td>clientid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">updateclientpassword</td><td class="ep-desc">{{ __('admin.api_docs.desc_updateclientpassword') }}</td><td>clientid, password (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">validatelogin</td><td class="ep-desc">{{ __('admin.api_docs.desc_validatelogin') }}</td><td>email, password (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getclientgroups</td><td class="ep-desc">{{ __('admin.api_docs.desc_getclientgroups') }}</td><td></td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">addclientnote</td><td class="ep-desc">{{ __('admin.api_docs.desc_addclientnote') }}</td><td>userid, note (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getclientnotes</td><td class="ep-desc">{{ __('admin.api_docs.desc_getclientnotes') }}</td><td>userid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getclientsaddons</td><td class="ep-desc">{{ __('admin.api_docs.desc_getclientsaddons') }}</td><td>clientid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getcontacts</td><td class="ep-desc">{{ __('admin.api_docs.desc_getcontacts') }}</td><td>userid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">addcontact</td><td class="ep-desc">{{ __('admin.api_docs.desc_addcontact') }}</td><td>clientid, email, firstname, lastname (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">updatecontact</td><td class="ep-desc">{{ __('admin.api_docs.desc_updatecontact') }}</td><td>contactid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">deletecontact</td><td class="ep-desc">{{ __('admin.api_docs.desc_deletecontact') }}</td><td>contactid (required)</td></tr>
        </tbody>
    </table>
</div>

{{-- INVOICES --}}
<div class="card api-section" id="section-invoices">
    <h3>{{ __('admin.api_docs.section_invoices') }}</h3>
    <table class="api-table">
        <thead><tr><th style="width:70px;">{{ __('admin.api_docs.method') }}</th><th style="width:220px;">{{ __('admin.api_docs.action') }}</th><th>{{ __('common.table.description') }}</th><th style="width:220px;">{{ __('admin.api_docs.key_params') }}</th></tr></thead>
        <tbody>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getinvoices</td><td class="ep-desc">{{ __('admin.api_docs.desc_getinvoices') }}</td><td>userid, status, limitnum, limitstart</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getinvoice</td><td class="ep-desc">{{ __('admin.api_docs.desc_getinvoice') }}</td><td>invoiceid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">createinvoice</td><td class="ep-desc">{{ __('admin.api_docs.desc_createinvoice') }}</td><td>userid, date, duedate, itemdescription[], itemamount[], paymentmethod</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">updateinvoice</td><td class="ep-desc">{{ __('admin.api_docs.desc_updateinvoice') }}</td><td>invoiceid (required), status, date, duedate</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">addinvoicepayment</td><td class="ep-desc">{{ __('admin.api_docs.desc_addinvoicepayment') }}</td><td>invoiceid, transid, gateway, date, amount (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">captureinvoice</td><td class="ep-desc">{{ __('admin.api_docs.desc_captureinvoice') }}</td><td>invoiceid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">sendinvoice</td><td class="ep-desc">{{ __('admin.api_docs.desc_sendinvoice') }}</td><td>invoiceid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">deleteinvoice</td><td class="ep-desc">{{ __('admin.api_docs.desc_deleteinvoice') }}</td><td>invoiceid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getinvoiceitemtypes</td><td class="ep-desc">{{ __('admin.api_docs.desc_getinvoiceitemtypes') }}</td><td></td></tr>
        </tbody>
    </table>
</div>

{{-- ORDERS --}}
<div class="card api-section" id="section-orders">
    <h3>{{ __('admin.api_docs.section_orders') }}</h3>
    <table class="api-table">
        <thead><tr><th style="width:70px;">{{ __('admin.api_docs.method') }}</th><th style="width:220px;">{{ __('admin.api_docs.action') }}</th><th>{{ __('common.table.description') }}</th><th style="width:220px;">{{ __('admin.api_docs.key_params') }}</th></tr></thead>
        <tbody>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getorders</td><td class="ep-desc">{{ __('admin.api_docs.desc_getorders') }}</td><td>userid, status, limitnum, limitstart</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getorderstatuses</td><td class="ep-desc">{{ __('admin.api_docs.desc_getorderstatuses') }}</td><td></td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">addorder</td><td class="ep-desc">{{ __('admin.api_docs.desc_addorder') }}</td><td>clientid, pid[], billingcycle[], paymentmethod (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">acceptorder</td><td class="ep-desc">{{ __('admin.api_docs.desc_acceptorder') }}</td><td>orderid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">pendingorder</td><td class="ep-desc">{{ __('admin.api_docs.desc_pendingorder') }}</td><td>orderid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">cancelorder</td><td class="ep-desc">{{ __('admin.api_docs.desc_cancelorder') }}</td><td>orderid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">fraudorder</td><td class="ep-desc">{{ __('admin.api_docs.desc_fraudorder') }}</td><td>orderid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">deleteorder</td><td class="ep-desc">{{ __('admin.api_docs.desc_deleteorder') }}</td><td>orderid (required)</td></tr>
        </tbody>
    </table>
</div>

{{-- TICKETS --}}
<div class="card api-section" id="section-tickets">
    <h3>{{ __('admin.api_docs.section_tickets') }}</h3>
    <table class="api-table">
        <thead><tr><th style="width:70px;">{{ __('admin.api_docs.method') }}</th><th style="width:220px;">{{ __('admin.api_docs.action') }}</th><th>{{ __('common.table.description') }}</th><th style="width:220px;">{{ __('admin.api_docs.key_params') }}</th></tr></thead>
        <tbody>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">gettickets</td><td class="ep-desc">{{ __('admin.api_docs.desc_gettickets') }}</td><td>clientid, deptid, status, limitnum, limitstart</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getticket</td><td class="ep-desc">{{ __('admin.api_docs.desc_getticket') }}</td><td>ticketid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">openticket</td><td class="ep-desc">{{ __('admin.api_docs.desc_openticket') }}</td><td>deptid, clientid, subject, message (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">addticketreply</td><td class="ep-desc">{{ __('admin.api_docs.desc_addticketreply') }}</td><td>ticketid, message (required), adminid or clientid</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">updateticket</td><td class="ep-desc">{{ __('admin.api_docs.desc_updateticket') }}</td><td>ticketid (required), status, subject, deptid</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">deleteticket</td><td class="ep-desc">{{ __('admin.api_docs.desc_deleteticket') }}</td><td>ticketid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">deleteticketreply</td><td class="ep-desc">{{ __('admin.api_docs.desc_deleteticketreply') }}</td><td>ticketid, replyid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getsupportdepartments</td><td class="ep-desc">{{ __('admin.api_docs.desc_getsupportdepartments') }}</td><td>ignore_dept_assignments</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getsupportstatuses</td><td class="ep-desc">{{ __('admin.api_docs.desc_getsupportstatuses') }}</td><td>deptid</td></tr>
        </tbody>
    </table>
</div>

{{-- DOMAINS --}}
<div class="card api-section" id="section-domains">
    <h3>{{ __('admin.api_docs.section_domains') }}</h3>
    <table class="api-table">
        <thead><tr><th style="width:70px;">{{ __('admin.api_docs.method') }}</th><th style="width:220px;">{{ __('admin.api_docs.action') }}</th><th>{{ __('common.table.description') }}</th><th style="width:220px;">{{ __('admin.api_docs.key_params') }}</th></tr></thead>
        <tbody>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getclientsdomains</td><td class="ep-desc">{{ __('admin.api_docs.desc_getclientsdomains') }}</td><td>clientid (required), limitnum, limitstart</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getdomains</td><td class="ep-desc">{{ __('admin.api_docs.desc_getdomains') }}</td><td>limitnum, limitstart, domain</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">domainwhois</td><td class="ep-desc">{{ __('admin.api_docs.desc_domainwhois') }}</td><td>domain (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">domainregister</td><td class="ep-desc">{{ __('admin.api_docs.desc_domainregister') }}</td><td>domainid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">domainrenew</td><td class="ep-desc">{{ __('admin.api_docs.desc_domainrenew') }}</td><td>domainid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">domaintransfer</td><td class="ep-desc">{{ __('admin.api_docs.desc_domaintransfer') }}</td><td>domainid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">domainrelease</td><td class="ep-desc">{{ __('admin.api_docs.desc_domainrelease') }}</td><td>domainid, newtag (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">domainsync</td><td class="ep-desc">{{ __('admin.api_docs.desc_domainsync') }}</td><td>domainid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">domainupdatelockstatus</td><td class="ep-desc">{{ __('admin.api_docs.desc_domainupdatelockstatus') }}</td><td>domainid, lockstatus (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getregistrars</td><td class="ep-desc">{{ __('admin.api_docs.desc_getregistrars') }}</td><td></td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">gettldpricing</td><td class="ep-desc">{{ __('admin.api_docs.desc_gettldpricing') }}</td><td>currencyid</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">updatedomain</td><td class="ep-desc">{{ __('admin.api_docs.desc_updatedomain') }}</td><td>domainid (required), expirydate, status, registrar, regdate</td></tr>
        </tbody>
    </table>
</div>

{{-- SERVICES --}}
<div class="card api-section" id="section-services">
    <h3>{{ __('admin.api_docs.section_services') }}</h3>
    <table class="api-table">
        <thead><tr><th style="width:70px;">{{ __('admin.api_docs.method') }}</th><th style="width:220px;">{{ __('admin.api_docs.action') }}</th><th>{{ __('common.table.description') }}</th><th style="width:220px;">{{ __('admin.api_docs.key_params') }}</th></tr></thead>
        <tbody>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getclientsproducts</td><td class="ep-desc">{{ __('admin.api_docs.desc_getclientsproducts') }}</td><td>clientid (required), limitnum, limitstart, pid, serviceid</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getproducts</td><td class="ep-desc">{{ __('admin.api_docs.desc_getproducts') }}</td><td>pid, gid, module</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">addservice</td><td class="ep-desc">{{ __('admin.api_docs.desc_addservice') }}</td><td>clientid, pid, paymentmethod (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">updateclientproduct</td><td class="ep-desc">{{ __('admin.api_docs.desc_updateclientproduct') }}</td><td>serviceid (required), any service field</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">modulecreate</td><td class="ep-desc">{{ __('admin.api_docs.desc_modulecreate') }}</td><td>serviceid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">moduleterminate</td><td class="ep-desc">{{ __('admin.api_docs.desc_moduleterminate') }}</td><td>serviceid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">modulesuspend</td><td class="ep-desc">{{ __('admin.api_docs.desc_modulesuspend') }}</td><td>serviceid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">moduleunsuspend</td><td class="ep-desc">{{ __('admin.api_docs.desc_moduleunsuspend') }}</td><td>serviceid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">modulechangepackage</td><td class="ep-desc">{{ __('admin.api_docs.desc_modulechangepackage') }}</td><td>serviceid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">modulechangepassword</td><td class="ep-desc">{{ __('admin.api_docs.desc_modulechangepassword') }}</td><td>serviceid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">modulenotifyexpiry</td><td class="ep-desc">{{ __('admin.api_docs.desc_modulenotifyexpiry') }}</td><td>serviceid (required)</td></tr>
        </tbody>
    </table>
</div>

{{-- QUOTES --}}
<div class="card api-section" id="section-quotes">
    <h3>{{ __('admin.api_docs.section_quotes') }}</h3>
    <table class="api-table">
        <thead><tr><th style="width:70px;">{{ __('admin.api_docs.method') }}</th><th style="width:220px;">{{ __('admin.api_docs.action') }}</th><th>{{ __('common.table.description') }}</th><th style="width:220px;">{{ __('admin.api_docs.key_params') }}</th></tr></thead>
        <tbody>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getquotes</td><td class="ep-desc">{{ __('admin.api_docs.desc_getquotes') }}</td><td>userid, status, limitnum, limitstart</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">createquote</td><td class="ep-desc">{{ __('admin.api_docs.desc_createquote') }}</td><td>userid, subject, validuntil, lineitems (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">updatequote</td><td class="ep-desc">{{ __('admin.api_docs.desc_updatequote') }}</td><td>quoteid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">deletequote</td><td class="ep-desc">{{ __('admin.api_docs.desc_deletequote') }}</td><td>quoteid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">sendquote</td><td class="ep-desc">{{ __('admin.api_docs.desc_sendquote') }}</td><td>quoteid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">acceptquote</td><td class="ep-desc">{{ __('admin.api_docs.desc_acceptquote') }}</td><td>quoteid (required)</td></tr>
        </tbody>
    </table>
</div>

{{-- PROJECTS --}}
<div class="card api-section" id="section-projects">
    <h3>{{ __('admin.api_docs.section_projects') }}</h3>
    <table class="api-table">
        <thead><tr><th style="width:70px;">{{ __('admin.api_docs.method') }}</th><th style="width:220px;">{{ __('admin.api_docs.action') }}</th><th>{{ __('common.table.description') }}</th><th style="width:220px;">{{ __('admin.api_docs.key_params') }}</th></tr></thead>
        <tbody>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getprojects</td><td class="ep-desc">{{ __('admin.api_docs.desc_getprojects') }}</td><td>limitnum, limitstart</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getproject</td><td class="ep-desc">{{ __('admin.api_docs.desc_getproject') }}</td><td>projectid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">createproject</td><td class="ep-desc">{{ __('admin.api_docs.desc_createproject') }}</td><td>title, adminid, clientid, status (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">updateproject</td><td class="ep-desc">{{ __('admin.api_docs.desc_updateproject') }}</td><td>projectid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">deleteproject</td><td class="ep-desc">{{ __('admin.api_docs.desc_deleteproject') }}</td><td>projectid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">addprojecttask</td><td class="ep-desc">{{ __('admin.api_docs.desc_addprojecttask') }}</td><td>projectid, title (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">updateprojecttask</td><td class="ep-desc">{{ __('admin.api_docs.desc_updateprojecttask') }}</td><td>projectid, taskid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">deleteprojecttask</td><td class="ep-desc">{{ __('admin.api_docs.desc_deleteprojecttask') }}</td><td>projectid, taskid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">addprojectmessage</td><td class="ep-desc">{{ __('admin.api_docs.desc_addprojectmessage') }}</td><td>projectid, message, adminid (required)</td></tr>
        </tbody>
    </table>
</div>

{{-- BILLING --}}
<div class="card api-section" id="section-billing">
    <h3>{{ __('admin.api_docs.section_billing') }}</h3>
    <table class="api-table">
        <thead><tr><th style="width:70px;">{{ __('admin.api_docs.method') }}</th><th style="width:220px;">{{ __('admin.api_docs.action') }}</th><th>{{ __('common.table.description') }}</th><th style="width:220px;">{{ __('admin.api_docs.key_params') }}</th></tr></thead>
        <tbody>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">gettransactions</td><td class="ep-desc">{{ __('admin.api_docs.desc_gettransactions') }}</td><td>clientid, invoiceid, limitnum, limitstart</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">addtransaction</td><td class="ep-desc">{{ __('admin.api_docs.desc_addtransaction') }}</td><td>clientid, invoiceid, description, amountin, amountout, gateway, date (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getcurrencies</td><td class="ep-desc">{{ __('admin.api_docs.desc_getcurrencies') }}</td><td></td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">addcredit</td><td class="ep-desc">{{ __('admin.api_docs.desc_addcredit') }}</td><td>clientid, description, amount (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">applycredit</td><td class="ep-desc">{{ __('admin.api_docs.desc_applycredit') }}</td><td>clientid, invoiceid, amount (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">addpaymentmethod</td><td class="ep-desc">{{ __('admin.api_docs.desc_addpaymentmethod') }}</td><td>clientid, gateway, card_last_four, card_expiry (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">deletepaymentmethod</td><td class="ep-desc">{{ __('admin.api_docs.desc_deletepaymentmethod') }}</td><td>clientid, card_id (required)</td></tr>
        </tbody>
    </table>
</div>

{{-- PRODUCTS --}}
<div class="card api-section" id="section-products">
    <h3>{{ __('admin.api_docs.section_products') }}</h3>
    <table class="api-table">
        <thead><tr><th style="width:70px;">{{ __('admin.api_docs.method') }}</th><th style="width:220px;">{{ __('admin.api_docs.action') }}</th><th>{{ __('common.table.description') }}</th><th style="width:220px;">{{ __('admin.api_docs.key_params') }}</th></tr></thead>
        <tbody>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">addproduct</td><td class="ep-desc">{{ __('admin.api_docs.desc_addproduct') }}</td><td>name, gid, type, paytype (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">updateproduct</td><td class="ep-desc">{{ __('admin.api_docs.desc_updateproduct') }}</td><td>pid (required), any product field</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">deleteproduct</td><td class="ep-desc">{{ __('admin.api_docs.desc_deleteproduct') }}</td><td>pid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getproductgroups</td><td class="ep-desc">{{ __('admin.api_docs.desc_getproductgroups') }}</td><td></td></tr>
        </tbody>
    </table>
</div>

{{-- EMAILS --}}
<div class="card api-section" id="section-emails">
    <h3>{{ __('admin.api_docs.section_emails') }}</h3>
    <table class="api-table">
        <thead><tr><th style="width:70px;">{{ __('admin.api_docs.method') }}</th><th style="width:220px;">{{ __('admin.api_docs.action') }}</th><th>{{ __('common.table.description') }}</th><th style="width:220px;">{{ __('admin.api_docs.key_params') }}</th></tr></thead>
        <tbody>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">sendemail</td><td class="ep-desc">{{ __('admin.api_docs.desc_sendemail') }}</td><td>messagename, id (clientid/serviceid/invoiceid depending on template)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">sendclientsmail</td><td class="ep-desc">{{ __('admin.api_docs.desc_sendclientsmail') }}</td><td>clientid, subject, message (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getemailtemplate</td><td class="ep-desc">{{ __('admin.api_docs.desc_getemailtemplate') }}</td><td>name (required)</td></tr>
        </tbody>
    </table>
</div>

{{-- ADMINS --}}
<div class="card api-section" id="section-admins">
    <h3>{{ __('admin.api_docs.section_admins') }}</h3>
    <table class="api-table">
        <thead><tr><th style="width:70px;">{{ __('admin.api_docs.method') }}</th><th style="width:220px;">{{ __('admin.api_docs.action') }}</th><th>{{ __('common.table.description') }}</th><th style="width:220px;">{{ __('admin.api_docs.key_params') }}</th></tr></thead>
        <tbody>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getadmins</td><td class="ep-desc">{{ __('admin.api_docs.desc_getadmins') }}</td><td></td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getadmindetails</td><td class="ep-desc">{{ __('admin.api_docs.desc_getadmindetails') }}</td><td></td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">addadmin</td><td class="ep-desc">{{ __('admin.api_docs.desc_addadmin') }}</td><td>username, firstname, lastname, email, password, roleid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">updateadmin</td><td class="ep-desc">{{ __('admin.api_docs.desc_updateadmin') }}</td><td>adminid (required), any field</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">deleteadmin</td><td class="ep-desc">{{ __('admin.api_docs.desc_deleteadmin') }}</td><td>adminid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getadminroles</td><td class="ep-desc">{{ __('admin.api_docs.desc_getadminroles') }}</td><td></td></tr>
        </tbody>
    </table>
</div>

{{-- CODE EXAMPLES --}}
<div class="card api-section" id="section-examples">
    <h3>{{ __('admin.api_docs.section_examples') }}</h3>
    <div style="padding:16px;">

        <h4 style="font-size:14px;font-weight:700;margin:0 0 8px;">{{ __('admin.api_docs.example_get_clients') }}</h4>
        <div class="tab-btns">
            <button class="tab-btn active" onclick="switchTab('curl1','tab-curl1-btn')">cURL</button>
            <button class="tab-btn" onclick="switchTab('php1','tab-php1-btn')">PHP</button>
            <button class="tab-btn" onclick="switchTab('python1','tab-python1-btn')">Python</button>
        </div>
        <div id="curl1" class="tab-pane active">
<pre class="code-block">curl -X POST {{ url('/api/v1') }} \
  -d "identifier=YOUR_IDENTIFIER" \
  -d "secret=YOUR_SECRET" \
  -d "action=getclients" \
  -d "limitnum=25"</pre>
        </div>
        <div id="php1" class="tab-pane">
<pre class="code-block">&lt;?php
$url = '{{ url('/api/v1') }}';
$params = [
    'identifier' => 'YOUR_IDENTIFIER',
    'secret'     => 'YOUR_SECRET',
    'action'     => 'getclients',
    'limitnum'   => 25,
];
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = json_decode(curl_exec($ch), true);
curl_close($ch);
print_r($result);</pre>
        </div>
        <div id="python1" class="tab-pane">
<pre class="code-block">import requests

url = '{{ url('/api/v1') }}'
payload = {
    'identifier': 'YOUR_IDENTIFIER',
    'secret': 'YOUR_SECRET',
    'action': 'getclients',
    'limitnum': 25,
}
response = requests.post(url, data=payload)
print(response.json())</pre>
        </div>

        <hr style="margin:20px 0;">

        <h4 style="font-size:14px;font-weight:700;margin:0 0 8px;">{{ __('admin.api_docs.example_create_invoice') }}</h4>
        <div class="tab-btns">
            <button class="tab-btn active" onclick="switchTab('curl2','tab-curl2-btn')">cURL</button>
            <button class="tab-btn" onclick="switchTab('php2','tab-php2-btn')">PHP</button>
        </div>
        <div id="curl2" class="tab-pane active">
<pre class="code-block">curl -X POST {{ url('/api/v1') }} \
  -d "identifier=YOUR_IDENTIFIER" \
  -d "secret=YOUR_SECRET" \
  -d "action=createinvoice" \
  -d "userid=1" \
  -d "date=2026-01-01" \
  -d "duedate=2026-01-15" \
  -d "itemdescription[]=Hosting - January 2026" \
  -d "itemamount[]=29.99" \
  -d "paymentmethod=banktransfer"</pre>
        </div>
        <div id="php2" class="tab-pane">
<pre class="code-block">&lt;?php
$params = [
    'identifier'       => 'YOUR_IDENTIFIER',
    'secret'           => 'YOUR_SECRET',
    'action'           => 'createinvoice',
    'userid'           => 1,
    'date'             => '2026-01-01',
    'duedate'          => '2026-01-15',
    'itemdescription'  => ['Hosting - January 2026'],
    'itemamount'       => [29.99],
    'paymentmethod'    => 'banktransfer',
];
// ... send POST request ...</pre>
        </div>

        <hr style="margin:20px 0;">

        <h4 style="font-size:14px;font-weight:700;margin:0 0 8px;">{{ __('admin.api_docs.example_response') }}</h4>
<pre class="code-block">{
  "result": "success",        {{ __('admin.api_docs.response_success_comment') }}
  "totalresults": 25,         {{ __('admin.api_docs.response_total_comment') }}
  "startnumber": 0,           {{ __('admin.api_docs.response_start_comment') }}
  "numreturned": 10,          {{ __('admin.api_docs.response_num_comment') }}
  "clients": {                {{ __('admin.api_docs.response_data_comment') }}
    "client": [ ... ]
  }
}

{{ __('admin.api_docs.response_error_comment') }}
{
  "result": "error",
  "message": "Authentication Failed"
}</pre>

    </div>
</div>

@push('scripts')
<script>
function switchTab(paneId, btnClass) {
    // Find parent container
    var pane = document.getElementById(paneId);
    if (!pane) return;
    var container = pane.parentElement;
    // Deactivate all panes and buttons in container
    container.querySelectorAll('.tab-pane').forEach(function(p) { p.classList.remove('active'); });
    container.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
    // Activate clicked
    pane.classList.add('active');
    event.target.classList.add('active');
}
</script>
@endpush

@endsection
