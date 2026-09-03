<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make email templates multilingual.
 *
 * Before this, email_templates.name was globally unique and every template
 * existed once, in whatever language it had been written in. This releases
 * the unique constraint, groups templates by (name, language), copies the
 * English templates into every active language as untranslated (custom=false)
 * seeds, and ships a Polish translation for the pl copies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_templates', function (Blueprint $table) {
            $table->dropUnique('email_templates_name_unique');
            $table->unique(['name', 'language'], 'email_templates_name_language_unique');
        });

        $languages = DB::table('languages')->pluck('code');

        foreach ($languages as $lang) {
            if ($lang === 'en') {
                continue;
            }

            foreach ($this->templates() as $t) {
                $exists = DB::table('email_templates')
                    ->where('name', $t['name'])
                    ->where('language', $lang)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('email_templates')->insert([
                    'type' => $t['type'],
                    'name' => $t['name'],
                    'subject' => $t['subject'],
                    'message' => $t['message'],
                    'language' => $lang,
                    'custom' => false,
                    'disabled' => false,
                    'plaintext' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Polish: replace the copied English content with a translation. Only
        // the copies seeded above are touched; a template an operator already
        // customised keeps their wording.
        $pl = $this->polish();
        foreach ($pl as $name => $t) {
            DB::table('email_templates')
                ->where('name', $name)
                ->where('language', 'pl')
                ->where('custom', false)
                ->update([
                    'subject' => $t['subject'],
                    'message' => $t['message'],
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('email_templates', function (Blueprint $table) {
            $table->dropUnique('email_templates_name_language_unique');
            $table->unique('name');
        });

        DB::table('email_templates')->where('language', '<>', 'en')->delete();
    }

    /**
     * The canonical English templates, as seeded.
     *
     * @return array<int, array{type: string, name: string, subject: string, message: string}>
     */
    private function templates(): array
    {
        return [
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
            ["type" => "product", "name" => "App Connection Details", "subject" => "Your {app_name} connection details", "message" => "Dear {client_name},\n\nHere are the connection details of the app you installed. Keep this message safe - it contains generated passwords.\n\n{app_details}\n\n{CompanyName}"],
        ];
    }

    /**
     * Polish translations, keyed by template name.
     *
     * @return array<string, array{subject: string, message: string}>
     */
    private function polish(): array
    {
        return [
            "Account Signup Email" => [
                "subject" => "Witamy w {CompanyName}",
                "message" => "Szanowny {client_name},\n\nDziękujemy za rejestrację w {CompanyName}.\n\nTwoje konto zostało utworzone, możesz się zalogować pod adresem {whmcs_url}\n\n{CompanyName}",
            ],
            "Password Reset Confirmation" => [
                "subject" => "Resetowanie hasła - {CompanyName}",
                "message" => "Szanowny {client_name},\n\nPoproszono o zresetowanie hasła do Twojego konta.\n\nKliknij tutaj, aby zresetować: {reset_url}\n\n{CompanyName}",
            ],
            "Password Reset Validation" => [
                "subject" => "Potwierdzenie resetowania hasła - {CompanyName}",
                "message" => "Szanowny {client_name},\n\nTwoje hasło zostało pomyślnie zresetowane.\n\n{CompanyName}",
            ],
            "Invoice Created" => [
                "subject" => "Nowa faktura #{invoice_num} - {CompanyName}",
                "message" => "Szanowny {client_name},\n\nDla Twojego konta wygenerowano nową fakturę #{invoice_num}.\n\nDo zapłaty: {invoice_total}\nTermin płatności: {invoice_due_date}\n\n{CompanyName}",
            ],
            "Invoice Payment Confirmation" => [
                "subject" => "Faktura #{invoice_num} - otrzymano płatność - {CompanyName}",
                "message" => "Szanowny {client_name},\n\nDziękujemy za płatność.\n\nFaktura: #{invoice_num}\nKwota: {invoice_total}\n\n{CompanyName}",
            ],
            "Invoice Reminder" => [
                "subject" => "Przypomnienie o fakturze #{invoice_num} - {CompanyName}",
                "message" => "Szanowny {client_name},\n\nTo przypomnienie, że faktura #{invoice_num} na kwotę {invoice_total} ma termin płatności {invoice_due_date}.\n\n{CompanyName}",
            ],
            "Invoice Overdue" => [
                "subject" => "Faktura #{invoice_num} po terminie - {CompanyName}",
                "message" => "Szanowny {client_name},\n\nFaktura #{invoice_num} na kwotę {invoice_total} jest już po terminie.\n\nProsimy o natychmiastową płatność, aby uniknąć zawieszenia usługi.\n\n{CompanyName}",
            ],
            "Service Welcome Email" => [
                "subject" => "Usługa aktywowana - {CompanyName}",
                "message" => "Szanowny {client_name},\n\nTwoja usługa {product_name} została aktywowana.\n\nDomena: {service_domain}\n\n{CompanyName}",
            ],
            "Service Suspension" => [
                "subject" => "Usługa zawieszona - {CompanyName}",
                "message" => "Szanowny {client_name},\n\nTwoja usługa {product_name} ({service_domain}) została zawieszona.\n\nPowód: {suspend_reason}\n\n{CompanyName}",
            ],
            "Service Unsuspension" => [
                "subject" => "Usługa przywrócona - {CompanyName}",
                "message" => "Szanowny {client_name},\n\nTwoja usługa {product_name} ({service_domain}) została przywrócona.\n\n{CompanyName}",
            ],
            "Service Termination" => [
                "subject" => "Usługa zakończona - {CompanyName}",
                "message" => "Szanowny {client_name},\n\nTwoja usługa {product_name} ({service_domain}) została zakończona.\n\n{CompanyName}",
            ],
            "Cancellation Confirmation" => [
                "subject" => "Potwierdzenie anulowania - {CompanyName}",
                "message" => "Szanowny {client_name},\n\nTwoje zgłoszenie anulowania usługi {product_name} zostało potwierdzone.\n\n{CompanyName}",
            ],
            "Domain Registration Confirmation" => [
                "subject" => "Domena {domain} zarejestrowana - {CompanyName}",
                "message" => "Szanowny {client_name},\n\nTwoja domena {domain} została zarejestrowana.\n\nData rejestracji: {reg_date}\nData wygaśnięcia: {expiry_date}\n\n{CompanyName}",
            ],
            "Domain Renewal Reminder" => [
                "subject" => "Przypomnienie o odnowieniu domeny {domain} - {CompanyName}",
                "message" => "Szanowny {client_name},\n\nTwoja domena {domain} wymaga odnowienia do dnia {expiry_date}.\n\n{CompanyName}",
            ],
            "Support Ticket Opened" => [
                "subject" => "[Zgłoszenie #{ticket_id}] {ticket_subject}",
                "message" => "Szanowny {client_name},\n\nOtwarto nowe zgłoszenie pomocy.\n\nNumer zgłoszenia: #{ticket_id}\nDział: {ticket_dept}\nTemat: {ticket_subject}\n\n{ticket_message}\n\n{CompanyName}",
            ],
            "Support Ticket Reply" => [
                "subject" => "Odp: [Zgłoszenie #{ticket_id}] {ticket_subject}",
                "message" => "Szanowny {client_name},\n\nDodano odpowiedź do zgłoszenia #{ticket_id}.\n\n{ticket_reply}\n\n{CompanyName}",
            ],
            "Support Ticket Closed" => [
                "subject" => "[Zgłoszenie #{ticket_id}] zamknięte - {ticket_subject}",
                "message" => "Szanowny {client_name},\n\nZgłoszenie #{ticket_id} zostało zamknięte.\n\n{CompanyName}",
            ],
            "Order Confirmation" => [
                "subject" => "Potwierdzenie zamówienia #{order_num} - {CompanyName}",
                "message" => "Szanowny {client_name},\n\nDziękujemy za zamówienie #{order_num}.\n\nSuma zamówienia: {order_total}\n\n{CompanyName}",
            ],
            "Credit Card Expiry Notice" => [
                "subject" => "Twoja karta wkrótce wygaśnie - {CompanyName}",
                "message" => "Szanowny {client_name},\n\nKarta, którą przechowujemy dla Twojego konta, wkrótce wygaśnie. Zaktualizuj ją w {whmcs_url}, aby kolejna faktura nie została odrzucona.\n\n{CompanyName}",
            ],
            "Payment Notification Rejected" => [
                "subject" => "Nie udało się zweryfikować płatności do faktury #{invoice_num} - {CompanyName}",
                "message" => "Szanowny {client_name},\n\nNie udało się dopasować zgłoszonej przez Ciebie płatności do faktury #{invoice_num}. Sprawdź tytuł przelewu i daj nam znać.\n\n{CompanyName}",
            ],
            "Login Email Changed" => [
                "subject" => "Zmieniono adres logowania na Twoim koncie {CompanyName}",
                "message" => "Szanowny {client_name},\n\nAdres logowania na Twoim koncie został zmieniony z {previous_email} na {new_email}. Jeśli to nie Ty, skontaktuj się z nami natychmiast.\n\n{CompanyName}",
            ],
            "SSL Certificate Issued" => [
                "subject" => "Wydano certyfikat SSL - {ssl_domain}",
                "message" => "Szanowny {client_name},\n\nCertyfikat dla {ssl_domain} został wydany i jest gotowy do instalacji. Możesz go pobrać w {whmcs_url}\n\n{CompanyName}",
            ],
            "SSL Certificate Expiring" => [
                "subject" => "Certyfikat SSL wygasa za {days_remaining} dni - {ssl_domain}",
                "message" => "Szanowny {client_name},\n\nCertyfikat dla {ssl_domain} wygasa za {days_remaining} dni. Odnów go w {whmcs_url}, aby uniknąć ostrzeżenia przeglądarki na swojej stronie.\n\n{CompanyName}",
            ],
            "SSL Configuration Required" => [
                "subject" => "Wymagana konfiguracja certyfikatu SSL - {ssl_domain}",
                "message" => "Szanowny {client_name},\n\nTwoje zamówienie certyfikatu dla {ssl_domain} czeka na podanie szczegółów. Uzupełnij je w {whmcs_url}\n\n{CompanyName}",
            ],
            "Affiliate Welcome Email" => [
                "subject" => "Program partnerski - {CompanyName}",
                "message" => "Szanowny {client_name},\n\nWitamy w naszym programie partnerskim!\n\nTwój link polecający: {affiliate_link}\n\n{CompanyName}",
            ],
            "App Connection Details" => [
                "subject" => "Dane połączenia aplikacji {app_name}",
                "message" => "Szanowny {client_name},\n\nOto dane połączenia zainstalowanej przez Ciebie aplikacji. Zachowaj tę wiadomość - zawiera wygenerowane hasła.\n\n{app_details}\n\n{CompanyName}",
            ],
        ];
    }
};
