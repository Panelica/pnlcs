@extends('admin.layouts.app')
@section('title', 'API Documentation')
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
    <h1>API Documentation</h1>
    <a href="{{ route('admin.config.api-credentials') }}" class="btn btn-default btn-sm">Manage API Keys</a>
</div>

{{-- Authentication --}}
<div class="card" style="margin-bottom:24px;">
    <div class="card-header" style="background:#f5f5f5;padding:12px 16px;border-bottom:1px solid #e5e5e5;">
        <h2 style="margin:0;font-size:16px;font-weight:700;">Authentication</h2>
    </div>
    <div class="card-body" style="padding:16px;">
        <p style="margin:0 0 12px;font-size:13px;color:#555;">All API requests require an <strong>identifier</strong> (API key) and <strong>secret</strong>. Send them as POST parameters with every request:</p>
        <table style="width:100%;border-collapse:collapse;font-size:13px;margin-bottom:16px;">
            <thead><tr><th style="text-align:left;padding:6px 10px;background:#fafafa;border-bottom:2px solid #e5e5e5;">Parameter</th><th style="text-align:left;padding:6px 10px;background:#fafafa;border-bottom:2px solid #e5e5e5;">Description</th></tr></thead>
            <tbody>
                <tr><td style="padding:6px 10px;border-bottom:1px solid #f0f0f0;"><code>identifier</code></td><td style="padding:6px 10px;border-bottom:1px solid #f0f0f0;">Your 32-character API key identifier</td></tr>
                <tr><td style="padding:6px 10px;border-bottom:1px solid #f0f0f0;"><code>secret</code></td><td style="padding:6px 10px;border-bottom:1px solid #f0f0f0;">Your 64-character API secret (shown only once at creation)</td></tr>
                <tr><td style="padding:6px 10px;"><code>action</code></td><td style="padding:6px 10px;">The API action to execute (e.g. <code>getclients</code>)</td></tr>
            </tbody>
        </table>
        <p style="font-size:13px;color:#555;margin:0 0 8px;"><strong>Base URL:</strong> <code>POST {{ url('/api/v1') }}</code></p>
        <p style="font-size:12px;color:#888;margin:0;">All requests are POST. Responses are JSON with a <code>result</code> field (<code>success</code> or <code>error</code>) and a <code>message</code> field on error.</p>
    </div>
</div>

{{-- Table of Contents --}}
<div class="card" style="margin-bottom:24px;">
    <div class="card-header" style="background:#f5f5f5;padding:12px 16px;border-bottom:1px solid #e5e5e5;">
        <h2 style="margin:0;font-size:16px;font-weight:700;">Table of Contents</h2>
    </div>
    <div class="card-body" style="padding:16px;">
        <ul class="toc-list">
            <li><a href="#section-system">System</a></li>
            <li><a href="#section-clients">Clients</a></li>
            <li><a href="#section-invoices">Invoices</a></li>
            <li><a href="#section-orders">Orders</a></li>
            <li><a href="#section-tickets">Support Tickets</a></li>
            <li><a href="#section-domains">Domains</a></li>
            <li><a href="#section-services">Services / Hosting</a></li>
            <li><a href="#section-quotes">Quotes</a></li>
            <li><a href="#section-projects">Projects</a></li>
            <li><a href="#section-billing">Billing / Transactions</a></li>
            <li><a href="#section-products">Products</a></li>
            <li><a href="#section-emails">Emails</a></li>
            <li><a href="#section-admins">Admins</a></li>
            <li><a href="#section-examples">Code Examples</a></li>
        </ul>
    </div>
</div>

{{-- SYSTEM --}}
<div class="card api-section" id="section-system">
    <h3>System</h3>
    <table class="api-table">
        <thead><tr><th style="width:70px;">Method</th><th style="width:220px;">Action</th><th>Description</th><th style="width:220px;">Key Parameters</th></tr></thead>
        <tbody>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getstats</td><td class="ep-desc">Get system statistics (clients, invoices, orders, revenue)</td><td></td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">gethealthstatus</td><td class="ep-desc">Check system health and service status</td><td></td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">pnlcsdetails</td><td class="ep-desc">Get PNLCS installation details (version, URL, admin email)</td><td></td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">sendadminemail</td><td class="ep-desc">Send an email to all administrators</td><td>subject, message</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">addannouncement</td><td class="ep-desc">Create a new announcement</td><td>title, announcement, published</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getannouncements</td><td class="ep-desc">List all announcements</td><td>limitnum, limitstart</td></tr>
        </tbody>
    </table>
</div>

{{-- CLIENTS --}}
<div class="card api-section" id="section-clients">
    <h3>Clients</h3>
    <table class="api-table">
        <thead><tr><th style="width:70px;">Method</th><th style="width:220px;">Action</th><th>Description</th><th style="width:220px;">Key Parameters</th></tr></thead>
        <tbody>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getclients</td><td class="ep-desc">List clients with optional search/filtering</td><td>search, limitnum, limitstart, sorting, orderby</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getclientsdetails</td><td class="ep-desc">Get full details for a single client</td><td>clientid <em>or</em> email (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">addclient</td><td class="ep-desc">Create a new client account</td><td>firstname, lastname, email, password, address1, city, country, phonenumber</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">updateclient</td><td class="ep-desc">Update an existing client's details</td><td>clientid (required), any client field</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">deleteclient</td><td class="ep-desc">Delete a client account</td><td>clientid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getclientpassword</td><td class="ep-desc">Get a client's encrypted password hash</td><td>clientid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">updateclientpassword</td><td class="ep-desc">Update a client's password</td><td>clientid, password (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">validatelogin</td><td class="ep-desc">Validate client login credentials</td><td>email, password (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getclientgroups</td><td class="ep-desc">List all client groups</td><td></td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">addclientnote</td><td class="ep-desc">Add a note to a client's account</td><td>userid, note (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getclientnotes</td><td class="ep-desc">Get notes attached to a client</td><td>userid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getclientsaddons</td><td class="ep-desc">Get all addons for a client</td><td>clientid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getcontacts</td><td class="ep-desc">Get sub-contacts for a client</td><td>userid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">addcontact</td><td class="ep-desc">Add a sub-contact to a client</td><td>clientid, email, firstname, lastname (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">updatecontact</td><td class="ep-desc">Update a sub-contact</td><td>contactid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">deletecontact</td><td class="ep-desc">Delete a sub-contact</td><td>contactid (required)</td></tr>
        </tbody>
    </table>
</div>

{{-- INVOICES --}}
<div class="card api-section" id="section-invoices">
    <h3>Invoices</h3>
    <table class="api-table">
        <thead><tr><th style="width:70px;">Method</th><th style="width:220px;">Action</th><th>Description</th><th style="width:220px;">Key Parameters</th></tr></thead>
        <tbody>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getinvoices</td><td class="ep-desc">List invoices with optional filtering</td><td>userid, status, limitnum, limitstart</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getinvoice</td><td class="ep-desc">Get a single invoice with line items</td><td>invoiceid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">createinvoice</td><td class="ep-desc">Create a new invoice</td><td>userid, date, duedate, itemdescription[], itemamount[], paymentmethod</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">updateinvoice</td><td class="ep-desc">Update an existing invoice</td><td>invoiceid (required), status, date, duedate</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">addinvoicepayment</td><td class="ep-desc">Record a manual payment for an invoice</td><td>invoiceid, transid, gateway, date, amount (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">captureinvoice</td><td class="ep-desc">Attempt to capture payment for an invoice</td><td>invoiceid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">sendinvoice</td><td class="ep-desc">Send an invoice by email to the client</td><td>invoiceid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">deleteinvoice</td><td class="ep-desc">Delete an invoice</td><td>invoiceid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getinvoiceitemtypes</td><td class="ep-desc">Get available invoice item types</td><td></td></tr>
        </tbody>
    </table>
</div>

{{-- ORDERS --}}
<div class="card api-section" id="section-orders">
    <h3>Orders</h3>
    <table class="api-table">
        <thead><tr><th style="width:70px;">Method</th><th style="width:220px;">Action</th><th>Description</th><th style="width:220px;">Key Parameters</th></tr></thead>
        <tbody>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getorders</td><td class="ep-desc">List all orders with optional filtering</td><td>userid, status, limitnum, limitstart</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getorderstatuses</td><td class="ep-desc">Get available order status options</td><td></td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">addorder</td><td class="ep-desc">Create a new order for a client</td><td>clientid, pid[], billingcycle[], paymentmethod (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">acceptorder</td><td class="ep-desc">Accept/approve a pending order</td><td>orderid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">pendingorder</td><td class="ep-desc">Set an order back to pending</td><td>orderid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">cancelorder</td><td class="ep-desc">Cancel an order</td><td>orderid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">fraudorder</td><td class="ep-desc">Mark an order as fraudulent</td><td>orderid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">deleteorder</td><td class="ep-desc">Delete an order</td><td>orderid (required)</td></tr>
        </tbody>
    </table>
</div>

{{-- TICKETS --}}
<div class="card api-section" id="section-tickets">
    <h3>Support Tickets</h3>
    <table class="api-table">
        <thead><tr><th style="width:70px;">Method</th><th style="width:220px;">Action</th><th>Description</th><th style="width:220px;">Key Parameters</th></tr></thead>
        <tbody>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">gettickets</td><td class="ep-desc">List support tickets with optional filtering</td><td>clientid, deptid, status, limitnum, limitstart</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getticket</td><td class="ep-desc">Get a single ticket with replies</td><td>ticketid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">openticket</td><td class="ep-desc">Open a new support ticket</td><td>deptid, clientid, subject, message (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">addticketreply</td><td class="ep-desc">Add a reply to an existing ticket</td><td>ticketid, message (required), adminid or clientid</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">updateticket</td><td class="ep-desc">Update ticket status or details</td><td>ticketid (required), status, subject, deptid</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">deleteticket</td><td class="ep-desc">Delete a ticket and all its replies</td><td>ticketid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">deleteticketreply</td><td class="ep-desc">Delete a specific ticket reply</td><td>ticketid, replyid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getsupportdepartments</td><td class="ep-desc">List all support departments</td><td>ignore_dept_assignments</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getsupportstatuses</td><td class="ep-desc">Get ticket status counts</td><td>deptid</td></tr>
        </tbody>
    </table>
</div>

{{-- DOMAINS --}}
<div class="card api-section" id="section-domains">
    <h3>Domains</h3>
    <table class="api-table">
        <thead><tr><th style="width:70px;">Method</th><th style="width:220px;">Action</th><th>Description</th><th style="width:220px;">Key Parameters</th></tr></thead>
        <tbody>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getclientsdomains</td><td class="ep-desc">List domains for a specific client</td><td>clientid (required), limitnum, limitstart</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getdomains</td><td class="ep-desc">List all domains in the system</td><td>limitnum, limitstart, domain</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">domainwhois</td><td class="ep-desc">Perform a WHOIS lookup for a domain</td><td>domain (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">domainregister</td><td class="ep-desc">Register a new domain</td><td>domainid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">domainrenew</td><td class="ep-desc">Renew an existing domain</td><td>domainid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">domaintransfer</td><td class="ep-desc">Transfer a domain to this registrar</td><td>domainid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">domainrelease</td><td class="ep-desc">Release a domain to another registrar</td><td>domainid, newtag (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">domainsync</td><td class="ep-desc">Sync domain expiry and status from registrar</td><td>domainid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">domainupdatelockstatus</td><td class="ep-desc">Enable or disable domain transfer lock</td><td>domainid, lockstatus (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getregistrars</td><td class="ep-desc">List all configured domain registrars</td><td></td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">gettldpricing</td><td class="ep-desc">Get pricing for all TLDs</td><td>currencyid</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">updatedomain</td><td class="ep-desc">Update domain record details</td><td>domainid (required), expirydate, status, registrar, regdate</td></tr>
        </tbody>
    </table>
</div>

{{-- SERVICES --}}
<div class="card api-section" id="section-services">
    <h3>Services / Hosting</h3>
    <table class="api-table">
        <thead><tr><th style="width:70px;">Method</th><th style="width:220px;">Action</th><th>Description</th><th style="width:220px;">Key Parameters</th></tr></thead>
        <tbody>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getclientsproducts</td><td class="ep-desc">List services/products for a client</td><td>clientid (required), limitnum, limitstart, pid, serviceid</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getproducts</td><td class="ep-desc">List all products/packages in the system</td><td>pid, gid, module</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">addservice</td><td class="ep-desc">Manually create a hosting service for a client</td><td>clientid, pid, paymentmethod (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">updateclientproduct</td><td class="ep-desc">Update a client's hosting service record</td><td>serviceid (required), any service field</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">modulecreate</td><td class="ep-desc">Run the module Create function for a service</td><td>serviceid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">moduleterminate</td><td class="ep-desc">Run the module Terminate function</td><td>serviceid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">modulesuspend</td><td class="ep-desc">Suspend a hosting service</td><td>serviceid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">moduleunsuspend</td><td class="ep-desc">Unsuspend/reactivate a service</td><td>serviceid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">modulechangepackage</td><td class="ep-desc">Run Change Package on a service module</td><td>serviceid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">modulechangepassword</td><td class="ep-desc">Run Change Password on a service module</td><td>serviceid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">modulenotifyexpiry</td><td class="ep-desc">Send expiry notification for a service</td><td>serviceid (required)</td></tr>
        </tbody>
    </table>
</div>

{{-- QUOTES --}}
<div class="card api-section" id="section-quotes">
    <h3>Quotes</h3>
    <table class="api-table">
        <thead><tr><th style="width:70px;">Method</th><th style="width:220px;">Action</th><th>Description</th><th style="width:220px;">Key Parameters</th></tr></thead>
        <tbody>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getquotes</td><td class="ep-desc">List all quotes with optional filtering</td><td>userid, status, limitnum, limitstart</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">createquote</td><td class="ep-desc">Create a new quote for a client</td><td>userid, subject, validuntil, lineitems (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">updatequote</td><td class="ep-desc">Update an existing quote</td><td>quoteid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">deletequote</td><td class="ep-desc">Delete a quote</td><td>quoteid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">sendquote</td><td class="ep-desc">Send a quote to the client by email</td><td>quoteid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">acceptquote</td><td class="ep-desc">Mark a quote as accepted and optionally generate an invoice</td><td>quoteid (required)</td></tr>
        </tbody>
    </table>
</div>

{{-- PROJECTS --}}
<div class="card api-section" id="section-projects">
    <h3>Projects</h3>
    <table class="api-table">
        <thead><tr><th style="width:70px;">Method</th><th style="width:220px;">Action</th><th>Description</th><th style="width:220px;">Key Parameters</th></tr></thead>
        <tbody>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getprojects</td><td class="ep-desc">List all projects</td><td>limitnum, limitstart</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getproject</td><td class="ep-desc">Get a single project with tasks and messages</td><td>projectid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">createproject</td><td class="ep-desc">Create a new project</td><td>title, adminid, clientid, status (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">updateproject</td><td class="ep-desc">Update a project's details</td><td>projectid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">deleteproject</td><td class="ep-desc">Delete a project</td><td>projectid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">addprojecttask</td><td class="ep-desc">Add a task to a project</td><td>projectid, title (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">updateprojecttask</td><td class="ep-desc">Update a project task</td><td>projectid, taskid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">deleteprojecttask</td><td class="ep-desc">Delete a task from a project</td><td>projectid, taskid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">addprojectmessage</td><td class="ep-desc">Add a message/comment to a project</td><td>projectid, message, adminid (required)</td></tr>
        </tbody>
    </table>
</div>

{{-- BILLING --}}
<div class="card api-section" id="section-billing">
    <h3>Billing / Transactions</h3>
    <table class="api-table">
        <thead><tr><th style="width:70px;">Method</th><th style="width:220px;">Action</th><th>Description</th><th style="width:220px;">Key Parameters</th></tr></thead>
        <tbody>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">gettransactions</td><td class="ep-desc">List payment transactions with optional filtering</td><td>clientid, invoiceid, limitnum, limitstart</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">addtransaction</td><td class="ep-desc">Add a manual transaction record</td><td>clientid, invoiceid, description, amountin, amountout, gateway, date (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getcurrencies</td><td class="ep-desc">List all configured currencies</td><td></td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">addcredit</td><td class="ep-desc">Add credit to a client's account balance</td><td>clientid, description, amount (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">applycredit</td><td class="ep-desc">Apply client credit to an invoice</td><td>clientid, invoiceid, amount (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">addpaymentmethod</td><td class="ep-desc">Add a payment method for a client</td><td>clientid, gateway, card_last_four, card_expiry (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">deletepaymentmethod</td><td class="ep-desc">Remove a stored payment method</td><td>clientid, card_id (required)</td></tr>
        </tbody>
    </table>
</div>

{{-- PRODUCTS --}}
<div class="card api-section" id="section-products">
    <h3>Products</h3>
    <table class="api-table">
        <thead><tr><th style="width:70px;">Method</th><th style="width:220px;">Action</th><th>Description</th><th style="width:220px;">Key Parameters</th></tr></thead>
        <tbody>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">addproduct</td><td class="ep-desc">Create a new product/package</td><td>name, gid, type, paytype (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">updateproduct</td><td class="ep-desc">Update an existing product</td><td>pid (required), any product field</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">deleteproduct</td><td class="ep-desc">Delete a product</td><td>pid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getproductgroups</td><td class="ep-desc">List all product groups</td><td></td></tr>
        </tbody>
    </table>
</div>

{{-- EMAILS --}}
<div class="card api-section" id="section-emails">
    <h3>Emails</h3>
    <table class="api-table">
        <thead><tr><th style="width:70px;">Method</th><th style="width:220px;">Action</th><th>Description</th><th style="width:220px;">Key Parameters</th></tr></thead>
        <tbody>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">sendemail</td><td class="ep-desc">Send a template-based email to a client</td><td>messagename, id (clientid/serviceid/invoiceid depending on template)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">sendclientsmail</td><td class="ep-desc">Send an arbitrary email to a specific client</td><td>clientid, subject, message (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getemailtemplate</td><td class="ep-desc">Get a specific email template by name</td><td>name (required)</td></tr>
        </tbody>
    </table>
</div>

{{-- ADMINS --}}
<div class="card api-section" id="section-admins">
    <h3>Admins</h3>
    <table class="api-table">
        <thead><tr><th style="width:70px;">Method</th><th style="width:220px;">Action</th><th>Description</th><th style="width:220px;">Key Parameters</th></tr></thead>
        <tbody>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getadmins</td><td class="ep-desc">List all administrator accounts</td><td></td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getadmindetails</td><td class="ep-desc">Get details for the authenticated admin</td><td></td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">addadmin</td><td class="ep-desc">Create a new admin account</td><td>username, firstname, lastname, email, password, roleid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">updateadmin</td><td class="ep-desc">Update an admin account</td><td>adminid (required), any field</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">deleteadmin</td><td class="ep-desc">Delete an admin account</td><td>adminid (required)</td></tr>
            <tr><td><span class="badge-post">POST</span></td><td class="ep-url">getadminroles</td><td class="ep-desc">List all admin roles</td><td></td></tr>
        </tbody>
    </table>
</div>

{{-- CODE EXAMPLES --}}
<div class="card api-section" id="section-examples">
    <h3>Code Examples</h3>
    <div style="padding:16px;">

        <h4 style="font-size:14px;font-weight:700;margin:0 0 8px;">Get Clients</h4>
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

        <h4 style="font-size:14px;font-weight:700;margin:0 0 8px;">Create Invoice</h4>
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

        <h4 style="font-size:14px;font-weight:700;margin:0 0 8px;">Response Format</h4>
<pre class="code-block">{
  "result": "success",        // "success" or "error"
  "totalresults": 25,         // present on list endpoints
  "startnumber": 0,           // pagination offset
  "numreturned": 10,          // items in this response
  "clients": {                // data key varies by action
    "client": [ ... ]
  }
}

// Error response:
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
