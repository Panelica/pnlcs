<?php

/*
 * Italian email templates, as shipped. Keyed by template name (EmailTemplateSeeder).
 *
 * A new Italian language row is seeded from these instead of an English copy
 * (LanguageObserver), and installs that already have the English copies get
 * them by migration where the operator has not changed the text.
 *
 * Written in the informal "tu" form like the rest of the Italian translation.
 * Merge fields ({client_name} ...) are exactly the English template's.
 */

return [
    'Account Signup Email' => [
        'subject' => 'Benvenuto in {CompanyName}',
        'message' => "Gentile {client_name},\n\ngrazie per esserti registrato su {CompanyName}.\n\nIl tuo account è stato creato: puoi accedere da {whmcs_url}\n\nCordiali saluti,\n{CompanyName}",
    ],
    'Email Verification' => [
        'subject' => 'Conferma il tuo indirizzo email - {CompanyName}',
        'message' => "Gentile {client_name},\n\nconferma questo indirizzo email, così possiamo inviarti fatture e avvisi relativi al tuo account.\n\nIl tuo codice di conferma: {verification_code}\nInseriscilo nella pagina che te l'ha chiesto. È valido per {code_minutes} minuti.\n\nOppure conferma da qui: {verify_url}\nIl link è valido per 24 ore e conferma soltanto l'indirizzo.\n\nNon condividere questo codice o questo link con nessuno.\n\nCordiali saluti,\n{CompanyName}",
    ],
    'Password Reset Confirmation' => [
        'subject' => 'Reimpostazione della password - {CompanyName}',
        'message' => "Gentile {client_name},\n\nè stata richiesta la reimpostazione della password del tuo account.\n\nPer reimpostarla clicca qui: {reset_url}\n\nSe non sei stato tu a chiederla, puoi ignorare questo messaggio.\n\nCordiali saluti,\n{CompanyName}",
    ],
    'Password Reset Validation' => [
        'subject' => 'Password reimpostata - {CompanyName}',
        'message' => "Gentile {client_name},\n\nla tua password è stata reimpostata correttamente.\n\nCordiali saluti,\n{CompanyName}",
    ],
    'Invoice Created' => [
        'subject' => 'Nuova fattura n. {invoice_num} - {CompanyName}',
        'message' => "Gentile {client_name},\n\nè stata emessa la fattura n. {invoice_num} per il tuo account.\n\nImporto dovuto: {invoice_total}\nScadenza: {invoice_due_date}\n\nCordiali saluti,\n{CompanyName}",
    ],
    'Invoice Payment Confirmation' => [
        'subject' => 'Pagamento ricevuto per la fattura n. {invoice_num} - {CompanyName}',
        'message' => "Gentile {client_name},\n\ngrazie per il pagamento.\n\nFattura: n. {invoice_num}\nImporto: {invoice_total}\n\nCordiali saluti,\n{CompanyName}",
    ],
    'Invoice Reminder' => [
        'subject' => 'Promemoria fattura n. {invoice_num} - {CompanyName}',
        'message' => "Gentile {client_name},\n\nti ricordiamo che la fattura n. {invoice_num} di {invoice_total} scade il {invoice_due_date}.\n\nCordiali saluti,\n{CompanyName}",
    ],
    'Invoice Overdue' => [
        'subject' => 'Fattura n. {invoice_num} scaduta - {CompanyName}',
        'message' => "Gentile {client_name},\n\nla fattura n. {invoice_num} di {invoice_total} è scaduta.\n\nTi chiediamo di provvedere al pagamento al più presto per evitare la sospensione del servizio.\n\nCordiali saluti,\n{CompanyName}",
    ],
    'Service Welcome Email' => [
        'subject' => 'Servizio attivato - {CompanyName}',
        'message' => "Gentile {client_name},\n\nil tuo servizio {product_name} è stato attivato.\n\nDominio: {service_domain}\n\nCordiali saluti,\n{CompanyName}",
    ],
    'Service Suspension' => [
        'subject' => 'Servizio sospeso - {CompanyName}',
        'message' => "Gentile {client_name},\n\nil tuo servizio {product_name} ({service_domain}) è stato sospeso.\n\nMotivo: {suspend_reason}\n\nCordiali saluti,\n{CompanyName}",
    ],
    'Service Unsuspension' => [
        'subject' => 'Servizio riattivato - {CompanyName}',
        'message' => "Gentile {client_name},\n\nil tuo servizio {product_name} ({service_domain}) è stato riattivato.\n\nCordiali saluti,\n{CompanyName}",
    ],
    'Service Termination' => [
        'subject' => 'Servizio cessato - {CompanyName}',
        'message' => "Gentile {client_name},\n\nil tuo servizio {product_name} ({service_domain}) è stato cessato.\n\nCordiali saluti,\n{CompanyName}",
    ],
    'Cancellation Confirmation' => [
        'subject' => 'Cancellazione confermata - {CompanyName}',
        'message' => "Gentile {client_name},\n\nla tua richiesta di cancellazione per {product_name} è stata confermata.\n\nCordiali saluti,\n{CompanyName}",
    ],
    'Domain Registration Confirmation' => [
        'subject' => 'Dominio {domain} registrato - {CompanyName}',
        'message' => "Gentile {client_name},\n\nil tuo dominio {domain} è stato registrato.\n\nData di registrazione: {reg_date}\nData di scadenza: {expiry_date}\n\nCordiali saluti,\n{CompanyName}",
    ],
    'Domain Renewal Reminder' => [
        'subject' => 'Promemoria rinnovo del dominio {domain} - {CompanyName}',
        'message' => "Gentile {client_name},\n\nil tuo dominio {domain} è in scadenza il {expiry_date} e va rinnovato.\n\nCordiali saluti,\n{CompanyName}",
    ],
    'Support Ticket Opened' => [
        'subject' => '[Ticket #{ticket_id}] {ticket_subject}',
        'message' => "Gentile {client_name},\n\nè stato aperto un nuovo ticket di assistenza.\n\nID ticket: #{ticket_id}\nReparto: {ticket_dept}\nOggetto: {ticket_subject}\n\n{ticket_message}\n\nCordiali saluti,\n{CompanyName}",
    ],
    'Support Ticket Reply' => [
        'subject' => 'Re: [Ticket #{ticket_id}] {ticket_subject}',
        'message' => "Gentile {client_name},\n\nè stata aggiunta una risposta al ticket #{ticket_id}.\n\n{ticket_reply}\n\nCordiali saluti,\n{CompanyName}",
    ],
    'Support Ticket Closed' => [
        'subject' => '[Ticket #{ticket_id}] Chiuso - {ticket_subject}',
        'message' => "Gentile {client_name},\n\nil ticket #{ticket_id} è stato chiuso.\n\nCordiali saluti,\n{CompanyName}",
    ],
    'Order Confirmation' => [
        'subject' => 'Conferma ordine n. {order_num} - {CompanyName}',
        'message' => "Gentile {client_name},\n\ngrazie per il tuo ordine n. {order_num}.\n\nTotale ordine: {order_total}\n\nCordiali saluti,\n{CompanyName}",
    ],
    'Credit Card Expiry Notice' => [
        'subject' => 'La tua carta sta per scadere - {CompanyName}',
        'message' => "Gentile {client_name},\n\nla carta registrata sul tuo account sta per scadere. Aggiornala su {whmcs_url}, così la prossima fattura non verrà rifiutata.\n\nCordiali saluti,\n{CompanyName}",
    ],
    'Payment Notification Rejected' => [
        'subject' => 'La notifica di pagamento per la fattura n. {invoice_num} non è stata verificata - {CompanyName}',
        'message' => "Gentile {client_name},\n\nnon siamo riusciti ad associare il pagamento che ci hai segnalato alla fattura n. {invoice_num}. Controlla la causale e faccelo sapere.\n\nCordiali saluti,\n{CompanyName}",
    ],
    'Login Email Changed' => [
        'subject' => 'L\'indirizzo di accesso al tuo account {CompanyName} è stato cambiato',
        'message' => "Gentile {client_name},\n\nl'indirizzo di accesso al tuo account è stato cambiato da {previous_email} a {new_email}. Se non sei stato tu, contattaci immediatamente.\n\nCordiali saluti,\n{CompanyName}",
    ],
    'New Device Sign-in' => [
        'subject' => 'Nuovo accesso al tuo account {CompanyName}',
        'message' => "Gentile {client_name},\n\nè stato effettuato un accesso al tuo account da un dispositivo che non avevamo mai visto prima.\n\nQuando: {login_time}\nDispositivo: {login_device}\nIndirizzo IP: {login_ip}\n\nSe sei stato tu, non devi fare nulla. Se non sei stato tu, cambia la password su {whmcs_url} e disconnetti le sessioni che non riconosci dalla pagina Sicurezza.\n\nCordiali saluti,\n{CompanyName}",
    ],
    'Domain Move Offered' => [
        'subject' => 'Il dominio {domain} ti è stato offerto - {CompanyName}',
        'message' => "Gentile {client_name},\n\n{from_name} vorrebbe cederti il dominio {domain}.\n\nAccedi e accettalo o rifiutalo nella pagina Domini entro il {expires_on}:\n{client_area_url}/domains\n\nSe non ti aspettavi questa offerta, puoi ignorarla o rifiutarla.\n\nCordiali saluti,\n{CompanyName}",
    ],
    'Confirmation Code' => [
        'subject' => 'Il tuo codice di conferma - {CompanyName}',
        'message' => "Gentile {client_name},\n\nusa questo codice per confermare l'operazione che hai avviato nel tuo account:\n\n{confirmation_code}\n\nIl codice è valido per {code_minutes} minuti. Se non l'hai richiesto, qualcuno potrebbe aver effettuato l'accesso al tuo account: cambia la password.\n\nCordiali saluti,\n{CompanyName}",
    ],
    'SSL Certificate Issued' => [
        'subject' => 'Certificato SSL emesso - {ssl_domain}',
        'message' => "Gentile {client_name},\n\nil certificato per {ssl_domain} è stato emesso ed è pronto per l'installazione. Puoi scaricarlo da {whmcs_url}\n\nCordiali saluti,\n{CompanyName}",
    ],
    'SSL Certificate Expiring' => [
        'subject' => 'Certificato SSL in scadenza tra {days_remaining} giorni - {ssl_domain}',
        'message' => "Gentile {client_name},\n\nil certificato per {ssl_domain} scade tra {days_remaining} giorni. Rinnovalo su {whmcs_url} per evitare un avviso di sicurezza nel browser dei tuoi visitatori.\n\nCordiali saluti,\n{CompanyName}",
    ],
    'SSL Configuration Required' => [
        'subject' => 'Configurazione del certificato SSL richiesta - {ssl_domain}',
        'message' => "Gentile {client_name},\n\nil tuo ordine di certificato per {ssl_domain} è in attesa di alcuni dati da parte tua. Completalo su {whmcs_url}\n\nCordiali saluti,\n{CompanyName}",
    ],
    'Affiliate Welcome Email' => [
        'subject' => 'Programma di affiliazione - {CompanyName}',
        'message' => "Gentile {client_name},\n\nbenvenuto nel nostro programma di affiliazione!\n\nIl tuo link di referral: {affiliate_link}\n\nCordiali saluti,\n{CompanyName}",
    ],
    'Automatic Payment Failed' => [
        'subject' => 'Non siamo riusciti ad addebitare la fattura n. {invoice_num} - {CompanyName}',
        'message' => "Gentile {client_name},\n\nabbiamo provato ad addebitare {invoice_total} per la fattura n. {invoice_num} sulla carta che hai registrato con noi, ma l'addebito non è stato accettato.\n\nPuoi pagare la fattura, oppure registrare un'altra carta, su {whmcs_url}\n\nCordiali saluti,\n{CompanyName}",
    ],
    'Automatic Payment Authentication Required' => [
        'subject' => 'La tua banca ti chiede di confermare un pagamento - fattura n. {invoice_num}',
        'message' => "Gentile {client_name},\n\nla tua banca ha chiesto la tua conferma prima di autorizzare {invoice_total} per la fattura n. {invoice_num}. Dalla tua carta non è stato prelevato nulla.\n\nApri la fattura su {whmcs_url} e usa il pulsante \"Conferma con la tua banca\". Così si completa il pagamento che abbiamo già avviato e non ti viene addebitato due volte.\n\nCordiali saluti,\n{CompanyName}",
    ],
    'App Connection Details' => [
        'subject' => 'I dati di connessione della tua app {app_name}',
        'message' => "Gentile {client_name},\n\necco i dati di connessione dell'app che hai installato. Conserva questo messaggio con cura: contiene password generate automaticamente.\n\n{app_details}\n\nCordiali saluti,\n{CompanyName}",
    ],
];
