<?php
namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            ["type" => "general", "name" => "Account Signup Email", "subject" => "Welcome to {CompanyName}", "message" => "Dear {client_name},\n\nThank you for registering with {CompanyName}.\n\nYour account has been created and you can login at {whmcs_url}\n\n{CompanyName}"],
            ["type" => "general", "name" => "Password Reset Confirmation", "subject" => "Password Reset - {CompanyName}", "message" => "Dear {client_name},\n\nA password reset has been requested for your account.\n\nClick here to reset: {reset_url}\n\n{CompanyName}"],
            ["type" => "general", "name" => "Password Reset Validation", "subject" => "Password Reset Validation - {CompanyName}", "message" => "Dear {client_name},\n\nYour password has been successfully reset.\n\n{CompanyName}"],
            ["type" => "invoice", "name" => "Invoice Created", "subject" => "New Invoice #{invoice_num} - {CompanyName}", "message" => "Dear {client_name},\n\nA new invoice #{invoice_num} has been generated for your account.\n\nAmount Due: {invoice_total}\nDue Date: {invoice_due_date}\n\n{CompanyName}"],
            ["type" => "invoice", "name" => "Invoice Payment Confirmation", "subject" => "Invoice #{invoice_num} Payment Received - {CompanyName}", "message" => "Dear {client_name},\n\nThank you for your payment.\n\nInvoice: #{invoice_num}\nAmount: {invoice_total}\n\n{CompanyName}"],
            ["type" => "invoice", "name" => "Invoice Reminder", "subject" => "Invoice #{invoice_num} Reminder - {CompanyName}", "message" => "Dear {client_name},\n\nThis is a reminder that invoice #{invoice_num} for {invoice_total} is due on {invoice_due_date}.\n\n{CompanyName}"],
            ["type" => "invoice", "name" => "Invoice Overdue", "subject" => "Invoice #{invoice_num} Overdue - {CompanyName}", "message" => "Dear {client_name},\n\nInvoice #{invoice_num} for {invoice_total} is now overdue.\n\nPlease make payment immediately to avoid service suspension.\n\n{CompanyName}"],
            ["type" => "product", "name" => "Service Welcome Email", "subject" => "Service Activated - {CompanyName}", "message" => "Dear {client_name},\n\nYour service {product_name} has been activated.\n\nDomain: {service_domain}\n\n{CompanyName}"],
            ["type" => "product", "name" => "Service Suspension", "subject" => "Service Suspended - {CompanyName}", "message" => "Dear {client_name},\n\nYour service {product_name} ({service_domain}) has been suspended.\n\nReason: {suspend_reason}\n\n{CompanyName}"],
            ["type" => "product", "name" => "Service Unsuspension", "subject" => "Service Unsuspended - {CompanyName}", "message" => "Dear {client_name},\n\nYour service {product_name} ({service_domain}) has been unsuspended.\n\n{CompanyName}"],
            ["type" => "product", "name" => "Service Termination", "subject" => "Service Terminated - {CompanyName}", "message" => "Dear {client_name},\n\nYour service {product_name} ({service_domain}) has been terminated.\n\n{CompanyName}"],
            ["type" => "product", "name" => "Cancellation Confirmation", "subject" => "Cancellation Confirmed - {CompanyName}", "message" => "Dear {client_name},\n\nYour cancellation request for {product_name} has been confirmed.\n\n{CompanyName}"],
            ["type" => "domain", "name" => "Domain Registration Confirmation", "subject" => "Domain {domain} Registered - {CompanyName}", "message" => "Dear {client_name},\n\nYour domain {domain} has been registered.\n\nRegistration Date: {reg_date}\nExpiry Date: {expiry_date}\n\n{CompanyName}"],
            ["type" => "domain", "name" => "Domain Renewal Reminder", "subject" => "Domain {domain} Renewal Reminder - {CompanyName}", "message" => "Dear {client_name},\n\nYour domain {domain} is due for renewal on {expiry_date}.\n\n{CompanyName}"],
            ["type" => "support", "name" => "Support Ticket Opened", "subject" => "[Ticket #{ticket_id}] {ticket_subject}", "message" => "Dear {client_name},\n\nA new support ticket has been opened.\n\nTicket ID: #{ticket_id}\nDepartment: {ticket_dept}\nSubject: {ticket_subject}\n\n{ticket_message}\n\n{CompanyName}"],
            ["type" => "support", "name" => "Support Ticket Reply", "subject" => "Re: [Ticket #{ticket_id}] {ticket_subject}", "message" => "Dear {client_name},\n\nA reply has been added to ticket #{ticket_id}.\n\n{ticket_reply}\n\n{CompanyName}"],
            ["type" => "support", "name" => "Support Ticket Closed", "subject" => "[Ticket #{ticket_id}] Closed - {ticket_subject}", "message" => "Dear {client_name},\n\nTicket #{ticket_id} has been closed.\n\n{CompanyName}"],
            ["type" => "order", "name" => "Order Confirmation", "subject" => "Order Confirmation #{order_num} - {CompanyName}", "message" => "Dear {client_name},\n\nThank you for your order #{order_num}.\n\nOrder Total: {order_total}\n\n{CompanyName}"],
            ["type" => "invoice", "name" => "Credit Card Expiry Notice", "subject" => "Your card is expiring soon - {CompanyName}", "message" => "Dear {client_name},\n\nThe card we hold for your account is about to expire. Please update it at {whmcs_url} so your next invoice is not declined.\n\n{CompanyName}"],
            ["type" => "invoice", "name" => "Payment Notification Rejected", "subject" => "Payment notification for invoice #{invoice_num} could not be verified - {CompanyName}", "message" => "Dear {client_name},\n\nWe could not match the payment you told us about to invoice #{invoice_num}. Please check the reference and let us know.\n\n{CompanyName}"],
            ["type" => "general", "name" => "Login Email Changed", "subject" => "The sign-in address on your {CompanyName} account was changed", "message" => "Dear {client_name},\n\nThe sign-in address on your account was changed from {previous_email} to {new_email}. If this was not you, contact us at once.\n\n{CompanyName}"],
            ["type" => "product", "name" => "SSL Certificate Issued", "subject" => "SSL Certificate Issued - {ssl_domain}", "message" => "Dear {client_name},\n\nThe certificate for {ssl_domain} has been issued and is ready to install. You can download it at {whmcs_url}\n\n{CompanyName}"],
            ["type" => "product", "name" => "SSL Certificate Expiring", "subject" => "SSL Certificate Expiring in {days_remaining} Days - {ssl_domain}", "message" => "Dear {client_name},\n\nThe certificate for {ssl_domain} expires in {days_remaining} days. Renew it at {whmcs_url} to avoid a browser warning on your site.\n\n{CompanyName}"],
            ["type" => "product", "name" => "SSL Configuration Required", "subject" => "SSL Certificate Configuration Required - {ssl_domain}", "message" => "Dear {client_name},\n\nYour certificate order for {ssl_domain} is waiting for details from you. Complete it at {whmcs_url}\n\n{CompanyName}"],
            ["type" => "affiliate", "name" => "Affiliate Welcome Email", "subject" => "Affiliate Program - {CompanyName}", "message" => "Dear {client_name},\n\nWelcome to our affiliate program!\n\nYour referral link: {affiliate_link}\n\n{CompanyName}"],
        ];

        foreach ($templates as $t) {
            EmailTemplate::firstOrCreate(["name" => $t["name"]], $t);
        }
    }
}
