<?php

/*
 * German email templates, as shipped. Keyed by template name (EmailTemplateSeeder).
 *
 * Contributed by Dejan Wolf (WHOST, Austria).
 */

return [
    'App Connection Details' => [
        'subject' => 'Zugangsdaten für {app_name} – {CompanyName}',
        'message' => "Hallo {client_name},\n\nHier findest du die Zugangsdaten für deine installierte Anwendung. Bewahre diese Nachricht sicher auf, da sie automatisch erzeugte Passwörter enthält.\n\n{app_details}\n\nFreundliche Grüße\nDein {CompanyName}-Team",
    ],
    'Email Verification' => [
        'subject' => 'E-Mail-Adresse bestätigen – {CompanyName}',
        'message' => "Hallo {client_name},\n\nBitte bestätige deine E-Mail-Adresse, damit wir dir Rechnungen und wichtige Mitteilungen zu deinem Kundenkonto senden können.\n\nE-Mail-Adresse bestätigen: {verify_url}\n\nDieser Link ist 24 Stunden gültig.\n\nFreundliche Grüße\nDein {CompanyName}-Team",
    ],
    'Password Reset Confirmation' => [
        'subject' => 'Passwort zurücksetzen – {CompanyName}',
        'message' => "Hallo {client_name},\n\nFür dein Kundenkonto wurde eine Zurücksetzung des Passworts angefordert.\n\nHier kannst du dein Passwort zurücksetzen: {reset_url}\n\nFalls du diese Anfrage nicht gestellt hast, kannst du diese Nachricht ignorieren.\n\nFreundliche Grüße\nDein {CompanyName}-Team",
    ],
    'Password Reset Validation' => [
        'subject' => 'Passwort erfolgreich geändert – {CompanyName}',
        'message' => "Hallo {client_name},\n\nDein Passwort wurde erfolgreich zurückgesetzt.\n\nFalls du diese Änderung nicht selbst vorgenommen hast, kontaktiere uns bitte umgehend.\n\nFreundliche Grüße\nDein {CompanyName}-Team",
    ],
    'Invoice Created' => [
        'subject' => 'Neue Rechnung #{invoice_num} – {CompanyName}',
        'message' => "Hallo {client_name},\n\nFür dein Kundenkonto wurde die Rechnung #{invoice_num} erstellt.\n\nRechnungsbetrag: {invoice_total}\nFällig am: {invoice_due_date}\n\nFreundliche Grüße\nDein {CompanyName}-Team",
    ],
    'Invoice Payment Confirmation' => [
        'subject' => 'Zahlung für Rechnung #{invoice_num} erhalten – {CompanyName}',
        'message' => "Hallo {client_name},\n\nVielen Dank für deine Zahlung!\n\nRechnung: #{invoice_num}\nBetrag: {invoice_total}\n\nWir haben deine Zahlung erhalten.\n\nFreundliche Grüße\nDein {CompanyName}-Team",
    ],
    'Invoice Reminder' => [
        'subject' => 'Zahlungserinnerung: Rechnung #{invoice_num} – {CompanyName}',
        'message' => "Hallo {client_name},\n\nDies ist eine Erinnerung, dass die Rechnung #{invoice_num} über {invoice_total} am {invoice_due_date} fällig ist.\n\nFalls du bereits bezahlt hast, betrachte diese Nachricht bitte als gegenstandslos.\n\nFreundliche Grüße\nDein {CompanyName}-Team",
    ],
    'Invoice Overdue' => [
        'subject' => 'Rechnung #{invoice_num} überfällig – {CompanyName}',
        'message' => "Hallo {client_name},\n\nDie Rechnung #{invoice_num} über {invoice_total} ist bereits überfällig.\n\nBitte begleiche den offenen Betrag möglichst bald, um eine Sperrung deiner Dienste zu vermeiden.\n\nFreundliche Grüße\nDein {CompanyName}-Team",
    ],
    'Service Welcome Email' => [
        'subject' => 'Dein Dienst wurde aktiviert – {CompanyName}',
        'message' => "Hallo {client_name},\n\nDein Dienst {product_name} wurde erfolgreich aktiviert.\n\nDomain: {service_domain}\n\nFreundliche Grüße\nDein {CompanyName}-Team",
    ],
    'Service Suspension' => [
        'subject' => 'Dein Dienst wurde gesperrt – {CompanyName}',
        'message' => "Hallo {client_name},\n\nDein Dienst {product_name} ({service_domain}) wurde gesperrt.\n\nGrund: {suspend_reason}\n\nBitte kontaktiere uns, falls du Fragen dazu hast.\n\nFreundliche Grüße\nDein {CompanyName}-Team",
    ],
    'Service Unsuspension' => [
        'subject' => 'Dein Dienst wurde wieder freigeschaltet – {CompanyName}',
        'message' => "Hallo {client_name},\n\nDein Dienst {product_name} ({service_domain}) wurde wieder freigeschaltet.\n\nFreundliche Grüße\nDein {CompanyName}-Team",
    ],
    'Service Termination' => [
        'subject' => 'Dein Dienst wurde beendet – {CompanyName}',
        'message' => "Hallo {client_name},\n\nDein Dienst {product_name} ({service_domain}) wurde beendet.\n\nFreundliche Grüße\nDein {CompanyName}-Team",
    ],
    'Cancellation Confirmation' => [
        'subject' => 'Kündigung bestätigt – {CompanyName}',
        'message' => "Hallo {client_name},\n\nDeine Kündigungsanfrage für {product_name} wurde bestätigt.\n\nFreundliche Grüße\nDein {CompanyName}-Team",
    ],
    'Domain Renewal Reminder' => [
        'subject' => 'Erinnerung: Domain {domain} verlängern – {CompanyName}',
        'message' => "Hallo {client_name},\n\nDeine Domain {domain} steht am {expiry_date} zur Verlängerung an.\n\nBitte kümmere dich rechtzeitig um die Verlängerung, damit deine Domain nicht abläuft.\n\nFreundliche Grüße\nDein {CompanyName}-Team",
    ],
    'Support Ticket Opened' => [
        'subject' => '[Ticket #{ticket_id}] {ticket_subject}',
        'message' => "Hallo {client_name},\n\nEin neues Support-Ticket wurde eröffnet.\n\nTicketnummer: #{ticket_id}\nAbteilung: {ticket_dept}\nBetreff: {ticket_subject}\n\n{ticket_message}\n\nFreundliche Grüße\nDein {CompanyName}-Team",
    ],
    'Support Ticket Reply' => [
        'subject' => 'Re: [Ticket #{ticket_id}] {ticket_subject}',
        'message' => "Hallo {client_name},\n\nZu deinem Support-Ticket #{ticket_id} ist eine neue Antwort eingegangen.\n\n{ticket_reply}\n\nFreundliche Grüße\nDein {CompanyName}-Team",
    ],
    'Support Ticket Closed' => [
        'subject' => '[Ticket #{ticket_id}] Geschlossen – {ticket_subject}',
        'message' => "Hallo {client_name},\n\nDein Support-Ticket #{ticket_id} wurde geschlossen.\n\nFreundliche Grüße\nDein {CompanyName}-Team",
    ],
    'Order Confirmation' => [
        'subject' => 'Bestellbestätigung #{order_num} – {CompanyName}',
        'message' => "Hallo {client_name},\n\nVielen Dank für deine Bestellung #{order_num}!\n\nBestellbetrag: {order_total}\n\nFreundliche Grüße\nDein {CompanyName}-Team",
    ],
    'Credit Card Expiry Notice' => [
        'subject' => 'Deine Zahlungskarte läuft bald ab – {CompanyName}',
        'message' => "Hallo {client_name},\n\nDie für dein Kundenkonto hinterlegte Zahlungskarte läuft demnächst ab. Bitte aktualisiere deine Kartendaten unter {whmcs_url}, damit die nächste Zahlung nicht abgelehnt wird.\n\nFreundliche Grüße\nDein {CompanyName}-Team",
    ],
    'Payment Notification Rejected' => [
        'subject' => 'Zahlungsmeldung für Rechnung #{invoice_num} nicht bestätigt – {CompanyName}',
        'message' => "Hallo {client_name},\n\nWir konnten die von dir gemeldete Zahlung keiner Zahlung für Rechnung #{invoice_num} zuordnen. Bitte überprüfe den Verwendungszweck und kontaktiere uns.\n\nFreundliche Grüße\nDein {CompanyName}-Team",
    ],
    'Login Email Changed' => [
        'subject' => 'Anmelde-E-Mail-Adresse geändert – {CompanyName}',
        'message' => "Hallo {client_name},\n\nDie Anmelde-E-Mail-Adresse deines Kundenkontos wurde von {previous_email} auf {new_email} geändert.\n\nFalls du diese Änderung nicht selbst vorgenommen hast, kontaktiere uns bitte umgehend.\n\nFreundliche Grüße\nDein {CompanyName}-Team",
    ],
    'SSL Certificate Issued' => [
        'subject' => 'SSL-Zertifikat ausgestellt – {ssl_domain}',
        'message' => "Hallo {client_name},\n\nDas SSL-Zertifikat für {ssl_domain} wurde ausgestellt und kann installiert werden. Du kannst es unter {whmcs_url} herunterladen.\n\nFreundliche Grüße\nDein {CompanyName}-Team",
    ],
    'SSL Certificate Expiring' => [
        'subject' => 'SSL-Zertifikat läuft in {days_remaining} Tagen ab – {ssl_domain}',
        'message' => "Hallo {client_name},\n\nDas SSL-Zertifikat für {ssl_domain} läuft in {days_remaining} Tagen ab. Bitte verlängere es unter {whmcs_url}, damit Besucher deiner Website keine Sicherheitswarnung erhalten.\n\nFreundliche Grüße\nDein {CompanyName}-Team",
    ],
    'SSL Configuration Required' => [
        'subject' => 'SSL-Zertifikat: Konfiguration erforderlich – {ssl_domain}',
        'message' => "Hallo {client_name},\n\nFür deine SSL-Zertifikatsbestellung für {ssl_domain} fehlen noch Angaben. Bitte vervollständige diese unter {whmcs_url}.\n\nFreundliche Grüße\nDein {CompanyName}-Team",
    ],
    'Affiliate Welcome Email' => [
        'subject' => 'Willkommen im Partnerprogramm – {CompanyName}',
        'message' => "Hallo {client_name},\n\nWillkommen in unserem Partnerprogramm!\n\nDein persönlicher Empfehlungslink: {affiliate_link}\n\nFreundliche Grüße\nDein {CompanyName}-Team",
    ],
];
