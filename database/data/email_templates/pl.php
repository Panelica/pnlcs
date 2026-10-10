<?php

/*
 * Polish email templates, as shipped. Keyed by template name (EmailTemplateSeeder).
 *
 * The same texts 2026_09_03_000001_email_templates_multilingual wrote over the
 * Polish copies that existed then; from here they also reach a Polish language
 * added later (LanguageObserver) and a fresh install.
 */

return [
    'Account Signup Email' => [
        'subject' => 'Witamy w {CompanyName}',
        'message' => "Szanowny {client_name},\n\nDziękujemy za rejestrację w {CompanyName}.\n\nTwoje konto zostało utworzone, możesz się zalogować pod adresem {whmcs_url}\n\n{CompanyName}",
    ],
    'Password Reset Confirmation' => [
        'subject' => 'Resetowanie hasła - {CompanyName}',
        'message' => "Szanowny {client_name},\n\nPoproszono o zresetowanie hasła do Twojego konta.\n\nKliknij tutaj, aby zresetować: {reset_url}\n\n{CompanyName}",
    ],
    'Password Reset Validation' => [
        'subject' => 'Potwierdzenie resetowania hasła - {CompanyName}',
        'message' => "Szanowny {client_name},\n\nTwoje hasło zostało pomyślnie zresetowane.\n\n{CompanyName}",
    ],
    'Invoice Created' => [
        'subject' => 'Nowa faktura #{invoice_num} - {CompanyName}',
        'message' => "Szanowny {client_name},\n\nDla Twojego konta wygenerowano nową fakturę #{invoice_num}.\n\nDo zapłaty: {invoice_total}\nTermin płatności: {invoice_due_date}\n\n{CompanyName}",
    ],
    'Invoice Payment Confirmation' => [
        'subject' => 'Faktura #{invoice_num} - otrzymano płatność - {CompanyName}',
        'message' => "Szanowny {client_name},\n\nDziękujemy za płatność.\n\nFaktura: #{invoice_num}\nKwota: {invoice_total}\n\n{CompanyName}",
    ],
    'Invoice Reminder' => [
        'subject' => 'Przypomnienie o fakturze #{invoice_num} - {CompanyName}',
        'message' => "Szanowny {client_name},\n\nTo przypomnienie, że faktura #{invoice_num} na kwotę {invoice_total} ma termin płatności {invoice_due_date}.\n\n{CompanyName}",
    ],
    'Invoice Overdue' => [
        'subject' => 'Faktura #{invoice_num} po terminie - {CompanyName}',
        'message' => "Szanowny {client_name},\n\nFaktura #{invoice_num} na kwotę {invoice_total} jest już po terminie.\n\nProsimy o natychmiastową płatność, aby uniknąć zawieszenia usługi.\n\n{CompanyName}",
    ],
    'Service Welcome Email' => [
        'subject' => 'Usługa aktywowana - {CompanyName}',
        'message' => "Szanowny {client_name},\n\nTwoja usługa {product_name} została aktywowana.\n\nDomena: {service_domain}\n\n{CompanyName}",
    ],
    'Service Suspension' => [
        'subject' => 'Usługa zawieszona - {CompanyName}',
        'message' => "Szanowny {client_name},\n\nTwoja usługa {product_name} ({service_domain}) została zawieszona.\n\nPowód: {suspend_reason}\n\n{CompanyName}",
    ],
    'Service Unsuspension' => [
        'subject' => 'Usługa przywrócona - {CompanyName}',
        'message' => "Szanowny {client_name},\n\nTwoja usługa {product_name} ({service_domain}) została przywrócona.\n\n{CompanyName}",
    ],
    'Service Termination' => [
        'subject' => 'Usługa zakończona - {CompanyName}',
        'message' => "Szanowny {client_name},\n\nTwoja usługa {product_name} ({service_domain}) została zakończona.\n\n{CompanyName}",
    ],
    'Cancellation Confirmation' => [
        'subject' => 'Potwierdzenie anulowania - {CompanyName}',
        'message' => "Szanowny {client_name},\n\nTwoje zgłoszenie anulowania usługi {product_name} zostało potwierdzone.\n\n{CompanyName}",
    ],
    'Domain Registration Confirmation' => [
        'subject' => 'Domena {domain} zarejestrowana - {CompanyName}',
        'message' => "Szanowny {client_name},\n\nTwoja domena {domain} została zarejestrowana.\n\nData rejestracji: {reg_date}\nData wygaśnięcia: {expiry_date}\n\n{CompanyName}",
    ],
    'Domain Renewal Reminder' => [
        'subject' => 'Przypomnienie o odnowieniu domeny {domain} - {CompanyName}',
        'message' => "Szanowny {client_name},\n\nTwoja domena {domain} wymaga odnowienia do dnia {expiry_date}.\n\n{CompanyName}",
    ],
    'Support Ticket Opened' => [
        'subject' => '[Zgłoszenie #{ticket_id}] {ticket_subject}',
        'message' => "Szanowny {client_name},\n\nOtwarto nowe zgłoszenie pomocy.\n\nNumer zgłoszenia: #{ticket_id}\nDział: {ticket_dept}\nTemat: {ticket_subject}\n\n{ticket_message}\n\n{CompanyName}",
    ],
    'Support Ticket Reply' => [
        'subject' => 'Odp: [Zgłoszenie #{ticket_id}] {ticket_subject}',
        'message' => "Szanowny {client_name},\n\nDodano odpowiedź do zgłoszenia #{ticket_id}.\n\n{ticket_reply}\n\n{CompanyName}",
    ],
    'Support Ticket Closed' => [
        'subject' => '[Zgłoszenie #{ticket_id}] zamknięte - {ticket_subject}',
        'message' => "Szanowny {client_name},\n\nZgłoszenie #{ticket_id} zostało zamknięte.\n\n{CompanyName}",
    ],
    'Order Confirmation' => [
        'subject' => 'Potwierdzenie zamówienia #{order_num} - {CompanyName}',
        'message' => "Szanowny {client_name},\n\nDziękujemy za zamówienie #{order_num}.\n\nSuma zamówienia: {order_total}\n\n{CompanyName}",
    ],
    'Credit Card Expiry Notice' => [
        'subject' => 'Twoja karta wkrótce wygaśnie - {CompanyName}',
        'message' => "Szanowny {client_name},\n\nKarta, którą przechowujemy dla Twojego konta, wkrótce wygaśnie. Zaktualizuj ją w {whmcs_url}, aby kolejna faktura nie została odrzucona.\n\n{CompanyName}",
    ],
    'Payment Notification Rejected' => [
        'subject' => 'Nie udało się zweryfikować płatności do faktury #{invoice_num} - {CompanyName}',
        'message' => "Szanowny {client_name},\n\nNie udało się dopasować zgłoszonej przez Ciebie płatności do faktury #{invoice_num}. Sprawdź tytuł przelewu i daj nam znać.\n\n{CompanyName}",
    ],
    'Login Email Changed' => [
        'subject' => 'Zmieniono adres logowania na Twoim koncie {CompanyName}',
        'message' => "Szanowny {client_name},\n\nAdres logowania na Twoim koncie został zmieniony z {previous_email} na {new_email}. Jeśli to nie Ty, skontaktuj się z nami natychmiast.\n\n{CompanyName}",
    ],
    'SSL Certificate Issued' => [
        'subject' => 'Wydano certyfikat SSL - {ssl_domain}',
        'message' => "Szanowny {client_name},\n\nCertyfikat dla {ssl_domain} został wydany i jest gotowy do instalacji. Możesz go pobrać w {whmcs_url}\n\n{CompanyName}",
    ],
    'SSL Certificate Expiring' => [
        'subject' => 'Certyfikat SSL wygasa za {days_remaining} dni - {ssl_domain}',
        'message' => "Szanowny {client_name},\n\nCertyfikat dla {ssl_domain} wygasa za {days_remaining} dni. Odnów go w {whmcs_url}, aby uniknąć ostrzeżenia przeglądarki na swojej stronie.\n\n{CompanyName}",
    ],
    'SSL Configuration Required' => [
        'subject' => 'Wymagana konfiguracja certyfikatu SSL - {ssl_domain}',
        'message' => "Szanowny {client_name},\n\nTwoje zamówienie certyfikatu dla {ssl_domain} czeka na podanie szczegółów. Uzupełnij je w {whmcs_url}\n\n{CompanyName}",
    ],
    'Affiliate Welcome Email' => [
        'subject' => 'Program partnerski - {CompanyName}',
        'message' => "Szanowny {client_name},\n\nWitamy w naszym programie partnerskim!\n\nTwój link polecający: {affiliate_link}\n\n{CompanyName}",
    ],
    'App Connection Details' => [
        'subject' => 'Dane połączenia aplikacji {app_name}',
        'message' => "Szanowny {client_name},\n\nOto dane połączenia zainstalowanej przez Ciebie aplikacji. Zachowaj tę wiadomość - zawiera wygenerowane hasła.\n\n{app_details}\n\n{CompanyName}",
    ],
];
