<?php

/*
 * German email templates, as shipped. Keyed by template name (EmailTemplateSeeder).
 *
 * A new German language row is seeded from these instead of an English copy
 * (LanguageObserver), and installs that already have the English copies get
 * them by migration where the operator has not changed the text.
 *
 * Contributed by Dejan Wolf (WHOST, Austria); written in the formal "Sie" form
 * like the rest of the German translation. Merge fields ({client_name} ...) are
 * exactly the English template's.
 */

return [
    'Account Signup Email' => [
        'subject' => 'Willkommen bei {CompanyName}',
        'message' => "Guten Tag {client_name},\n\nVielen Dank für Ihre Registrierung bei {CompanyName}.\n\nIhr Kundenkonto wurde erstellt. Sie können sich unter {whmcs_url} anmelden.\n\nMit freundlichen Grüßen\nIhr {CompanyName}-Team",
    ],
    'Email Verification' => [
        'subject' => 'E-Mail-Adresse bestätigen – {CompanyName}',
        'message' => "Guten Tag {client_name},\n\nBitte bestätigen Sie Ihre E-Mail-Adresse, damit wir Ihnen Rechnungen und wichtige Mitteilungen zu Ihrem Kundenkonto senden können.\n\nE-Mail-Adresse bestätigen: {verify_url}\n\nDieser Link ist 24 Stunden gültig.\n\nMit freundlichen Grüßen\nIhr {CompanyName}-Team",
    ],
    'Password Reset Confirmation' => [
        'subject' => 'Passwort zurücksetzen – {CompanyName}',
        'message' => "Guten Tag {client_name},\n\nFür Ihr Kundenkonto wurde eine Zurücksetzung des Passworts angefordert.\n\nHier können Sie Ihr Passwort zurücksetzen: {reset_url}\n\nFalls Sie diese Anfrage nicht gestellt haben, können Sie diese Nachricht ignorieren.\n\nMit freundlichen Grüßen\nIhr {CompanyName}-Team",
    ],
    'Password Reset Validation' => [
        'subject' => 'Passwort erfolgreich geändert – {CompanyName}',
        'message' => "Guten Tag {client_name},\n\nIhr Passwort wurde erfolgreich zurückgesetzt.\n\nFalls Sie diese Änderung nicht selbst vorgenommen haben, kontaktieren Sie uns bitte umgehend.\n\nMit freundlichen Grüßen\nIhr {CompanyName}-Team",
    ],
    'Invoice Created' => [
        'subject' => 'Neue Rechnung #{invoice_num} – {CompanyName}',
        'message' => "Guten Tag {client_name},\n\nFür Ihr Kundenkonto wurde die Rechnung #{invoice_num} erstellt.\n\nRechnungsbetrag: {invoice_total}\nFällig am: {invoice_due_date}\n\nMit freundlichen Grüßen\nIhr {CompanyName}-Team",
    ],
    'Invoice Payment Confirmation' => [
        'subject' => 'Zahlung für Rechnung #{invoice_num} erhalten – {CompanyName}',
        'message' => "Guten Tag {client_name},\n\nVielen Dank für Ihre Zahlung!\n\nRechnung: #{invoice_num}\nBetrag: {invoice_total}\n\nWir haben Ihre Zahlung erhalten.\n\nMit freundlichen Grüßen\nIhr {CompanyName}-Team",
    ],
    'Invoice Reminder' => [
        'subject' => 'Zahlungserinnerung: Rechnung #{invoice_num} – {CompanyName}',
        'message' => "Guten Tag {client_name},\n\nDies ist eine Erinnerung, dass die Rechnung #{invoice_num} über {invoice_total} am {invoice_due_date} fällig ist.\n\nFalls Sie bereits bezahlt haben, betrachten Sie diese Nachricht bitte als gegenstandslos.\n\nMit freundlichen Grüßen\nIhr {CompanyName}-Team",
    ],
    'Invoice Overdue' => [
        'subject' => 'Rechnung #{invoice_num} überfällig – {CompanyName}',
        'message' => "Guten Tag {client_name},\n\nDie Rechnung #{invoice_num} über {invoice_total} ist bereits überfällig.\n\nBitte begleichen Sie den offenen Betrag möglichst bald, um eine Sperrung Ihrer Dienste zu vermeiden.\n\nMit freundlichen Grüßen\nIhr {CompanyName}-Team",
    ],
    'Service Welcome Email' => [
        'subject' => 'Ihr Dienst wurde aktiviert – {CompanyName}',
        'message' => "Guten Tag {client_name},\n\nIhr Dienst {product_name} wurde erfolgreich aktiviert.\n\nDomain: {service_domain}\n\nMit freundlichen Grüßen\nIhr {CompanyName}-Team",
    ],
    'Service Suspension' => [
        'subject' => 'Ihr Dienst wurde gesperrt – {CompanyName}',
        'message' => "Guten Tag {client_name},\n\nIhr Dienst {product_name} ({service_domain}) wurde gesperrt.\n\nGrund: {suspend_reason}\n\nBitte kontaktieren Sie uns, falls Sie Fragen dazu haben.\n\nMit freundlichen Grüßen\nIhr {CompanyName}-Team",
    ],
    'Service Unsuspension' => [
        'subject' => 'Ihr Dienst wurde wieder freigeschaltet – {CompanyName}',
        'message' => "Guten Tag {client_name},\n\nIhr Dienst {product_name} ({service_domain}) wurde wieder freigeschaltet.\n\nMit freundlichen Grüßen\nIhr {CompanyName}-Team",
    ],
    'Service Termination' => [
        'subject' => 'Ihr Dienst wurde beendet – {CompanyName}',
        'message' => "Guten Tag {client_name},\n\nIhr Dienst {product_name} ({service_domain}) wurde beendet.\n\nMit freundlichen Grüßen\nIhr {CompanyName}-Team",
    ],
    'Cancellation Confirmation' => [
        'subject' => 'Kündigung bestätigt – {CompanyName}',
        'message' => "Guten Tag {client_name},\n\nIhre Kündigungsanfrage für {product_name} wurde bestätigt.\n\nMit freundlichen Grüßen\nIhr {CompanyName}-Team",
    ],
    'Domain Registration Confirmation' => [
        'subject' => 'Domain {domain} registriert – {CompanyName}',
        'message' => "Guten Tag {client_name},\n\nIhre Domain {domain} wurde registriert.\n\nRegistrierungsdatum: {reg_date}\nAblaufdatum: {expiry_date}\n\nMit freundlichen Grüßen\nIhr {CompanyName}-Team",
    ],
    'Domain Renewal Reminder' => [
        'subject' => 'Erinnerung: Domain {domain} verlängern – {CompanyName}',
        'message' => "Guten Tag {client_name},\n\nIhre Domain {domain} steht am {expiry_date} zur Verlängerung an.\n\nBitte kümmern Sie sich rechtzeitig um die Verlängerung, damit Ihre Domain nicht abläuft.\n\nMit freundlichen Grüßen\nIhr {CompanyName}-Team",
    ],
    'Support Ticket Opened' => [
        'subject' => '[Ticket #{ticket_id}] {ticket_subject}',
        'message' => "Guten Tag {client_name},\n\nEin neues Support-Ticket wurde eröffnet.\n\nTicketnummer: #{ticket_id}\nAbteilung: {ticket_dept}\nBetreff: {ticket_subject}\n\n{ticket_message}\n\nMit freundlichen Grüßen\nIhr {CompanyName}-Team",
    ],
    'Support Ticket Reply' => [
        'subject' => 'Re: [Ticket #{ticket_id}] {ticket_subject}',
        'message' => "Guten Tag {client_name},\n\nZu Ihrem Support-Ticket #{ticket_id} ist eine neue Antwort eingegangen.\n\n{ticket_reply}\n\nMit freundlichen Grüßen\nIhr {CompanyName}-Team",
    ],
    'Support Ticket Closed' => [
        'subject' => '[Ticket #{ticket_id}] Geschlossen – {ticket_subject}',
        'message' => "Guten Tag {client_name},\n\nIhr Support-Ticket #{ticket_id} wurde geschlossen.\n\nMit freundlichen Grüßen\nIhr {CompanyName}-Team",
    ],
    'Order Confirmation' => [
        'subject' => 'Bestellbestätigung #{order_num} – {CompanyName}',
        'message' => "Guten Tag {client_name},\n\nVielen Dank für Ihre Bestellung #{order_num}!\n\nBestellbetrag: {order_total}\n\nMit freundlichen Grüßen\nIhr {CompanyName}-Team",
    ],
    'Credit Card Expiry Notice' => [
        'subject' => 'Ihre Zahlungskarte läuft bald ab – {CompanyName}',
        'message' => "Guten Tag {client_name},\n\nDie für Ihr Kundenkonto hinterlegte Zahlungskarte läuft demnächst ab. Bitte aktualisieren Sie Ihre Kartendaten unter {whmcs_url}, damit die nächste Zahlung nicht abgelehnt wird.\n\nMit freundlichen Grüßen\nIhr {CompanyName}-Team",
    ],
    'Payment Notification Rejected' => [
        'subject' => 'Zahlungsmeldung für Rechnung #{invoice_num} nicht bestätigt – {CompanyName}',
        'message' => "Guten Tag {client_name},\n\nWir konnten die von Ihnen gemeldete Zahlung der Rechnung #{invoice_num} nicht zuordnen. Bitte überprüfen Sie den Verwendungszweck und kontaktieren Sie uns.\n\nMit freundlichen Grüßen\nIhr {CompanyName}-Team",
    ],
    'Login Email Changed' => [
        'subject' => 'Anmelde-E-Mail-Adresse geändert – {CompanyName}',
        'message' => "Guten Tag {client_name},\n\nDie Anmelde-E-Mail-Adresse Ihres Kundenkontos wurde von {previous_email} auf {new_email} geändert.\n\nFalls Sie diese Änderung nicht selbst vorgenommen haben, kontaktieren Sie uns bitte umgehend.\n\nMit freundlichen Grüßen\nIhr {CompanyName}-Team",
    ],
    'New Device Sign-in' => [
        'subject' => 'Neue Anmeldung bei Ihrem {CompanyName}-Konto',
        'message' => "Guten Tag {client_name},\n\nBei Ihrem Kundenkonto gab es eine Anmeldung von einem Gerät, das wir noch nicht kennen.\n\nZeitpunkt: {login_time}\nGerät: {login_device}\nIP-Adresse: {login_ip}\n\nWenn Sie das waren, müssen Sie nichts tun. Falls nicht, ändern Sie Ihr Passwort unter {whmcs_url} und melden Sie auf der Seite „Sicherheit“ die Sitzungen ab, die Sie nicht kennen.\n\nMit freundlichen Grüßen\nIhr {CompanyName}-Team",
    ],
    'Domain Move Offered' => [
        'subject' => 'Domain {domain} wird Ihnen angeboten – {CompanyName}',
        'message' => "Guten Tag {client_name},\n\n{from_name} möchte Ihnen die Domain {domain} übertragen.\n\nMelden Sie sich an und nehmen Sie das Angebot bis {expires_on} auf der Seite „Domains“ an oder lehnen Sie es ab:\n{client_area_url}/domains\n\nFalls Sie damit nicht gerechnet haben, können Sie diese Nachricht ignorieren oder das Angebot ablehnen.\n\nMit freundlichen Grüßen\nIhr {CompanyName}-Team",
    ],
    'Confirmation Code' => [
        'subject' => 'Ihr Bestätigungscode – {CompanyName}',
        'message' => "Guten Tag {client_name},\n\nVerwenden Sie diesen Code, um die Aktion zu bestätigen, die Sie in Ihrem Kundenkonto begonnen haben:\n\n{confirmation_code}\n\nDer Code ist {code_minutes} Minuten gültig. Falls Sie ihn nicht angefordert haben, ist möglicherweise jemand in Ihrem Kundenkonto angemeldet: Ändern Sie Ihr Passwort.\n\nMit freundlichen Grüßen\nIhr {CompanyName}-Team",
    ],
    'SSL Certificate Issued' => [
        'subject' => 'SSL-Zertifikat ausgestellt – {ssl_domain}',
        'message' => "Guten Tag {client_name},\n\nDas SSL-Zertifikat für {ssl_domain} wurde ausgestellt und kann installiert werden. Sie können es unter {whmcs_url} herunterladen.\n\nMit freundlichen Grüßen\nIhr {CompanyName}-Team",
    ],
    'SSL Certificate Expiring' => [
        'subject' => 'SSL-Zertifikat läuft in {days_remaining} Tagen ab – {ssl_domain}',
        'message' => "Guten Tag {client_name},\n\nDas SSL-Zertifikat für {ssl_domain} läuft in {days_remaining} Tagen ab. Bitte verlängern Sie es unter {whmcs_url}, damit Besucher Ihrer Website keine Sicherheitswarnung erhalten.\n\nMit freundlichen Grüßen\nIhr {CompanyName}-Team",
    ],
    'SSL Configuration Required' => [
        'subject' => 'SSL-Zertifikat: Konfiguration erforderlich – {ssl_domain}',
        'message' => "Guten Tag {client_name},\n\nFür Ihre SSL-Zertifikatsbestellung für {ssl_domain} fehlen noch Angaben. Bitte vervollständigen Sie diese unter {whmcs_url}.\n\nMit freundlichen Grüßen\nIhr {CompanyName}-Team",
    ],
    'Affiliate Welcome Email' => [
        'subject' => 'Willkommen im Partnerprogramm – {CompanyName}',
        'message' => "Guten Tag {client_name},\n\nWillkommen in unserem Partnerprogramm!\n\nIhr persönlicher Empfehlungslink: {affiliate_link}\n\nMit freundlichen Grüßen\nIhr {CompanyName}-Team",
    ],
    'Automatic Payment Failed' => [
        'subject' => 'Zahlung für Rechnung #{invoice_num} fehlgeschlagen – {CompanyName}',
        'message' => "Guten Tag {client_name},\n\nWir haben versucht, {invoice_total} für die Rechnung #{invoice_num} von Ihrer hinterlegten Karte einzuziehen, die Zahlung wurde jedoch nicht akzeptiert.\n\nSie können die Rechnung unter {whmcs_url} bezahlen oder dort eine andere Karte hinterlegen.\n\nMit freundlichen Grüßen\nIhr {CompanyName}-Team",
    ],
    'Automatic Payment Authentication Required' => [
        'subject' => 'Ihre Bank bittet um Bestätigung einer Zahlung – Rechnung #{invoice_num}',
        'message' => "Guten Tag {client_name},\n\nIhre Bank möchte, dass Sie die Zahlung von {invoice_total} für die Rechnung #{invoice_num} bestätigen, bevor sie freigegeben wird. Von Ihrer Karte wurde noch nichts abgebucht.\n\nÖffnen Sie die Rechnung unter {whmcs_url} und klicken Sie dort auf „Bei Ihrer Bank bestätigen“. Damit wird die bereits begonnene Zahlung abgeschlossen, sodass Ihnen nichts doppelt berechnet wird.\n\nMit freundlichen Grüßen\nIhr {CompanyName}-Team",
    ],
    'App Connection Details' => [
        'subject' => 'Zugangsdaten für {app_name} – {CompanyName}',
        'message' => "Guten Tag {client_name},\n\nHier finden Sie die Zugangsdaten für Ihre installierte Anwendung. Bewahren Sie diese Nachricht sicher auf, da sie automatisch erzeugte Passwörter enthält.\n\n{app_details}\n\nMit freundlichen Grüßen\nIhr {CompanyName}-Team",
    ],
];
