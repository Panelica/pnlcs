# Configure Email

Email is **required**. Invoices, order confirmations, password resets, welcome
messages and ticket replies all go out by email. Until it's configured, PNLCS
writes messages to a log file and customers receive nothing.

## Set up SMTP

**Settings → Email** (or edit `.env` directly)

Set the mailer to `smtp` and fill in your mail server details:

| Field | Example |
|-------|---------|
| Mailer | `smtp` |
| Host | `smtp.your-provider.com` |
| Port | `587` (STARTTLS) or `465` (SSL) |
| Encryption | `tls` or `ssl` |
| Username | your SMTP username |
| Password | your SMTP password |
| From address | `noreply@your-domain.com` |
| From name | `Your Company` |

If you edit `.env` instead of the settings page, the keys are:

```ini
MAIL_MAILER=smtp
MAIL_HOST=smtp.your-provider.com
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@your-domain.com"
MAIL_FROM_NAME="Your Company"
```

## Send a test email

From **Settings → Email**, use the **Send test email** button. Check that it
arrives (including the spam folder). If it doesn't, see
[Email not sending](../troubleshooting/common-issues.md#emails-are-not-being-sent).

## Improve deliverability

To keep invoices out of spam folders, set up these DNS records for your sending
domain:

- **SPF** — authorize your mail server to send for the domain
- **DKIM** — sign outgoing mail (your mail provider gives you the record)
- **DMARC** — a policy record that ties SPF and DKIM together

A dedicated transactional email provider (Postmark, SES, Mailgun, SendGrid…)
generally lands in the inbox more reliably than a self-hosted SMTP server.

## Email templates

PNLCS ships with ready-made templates (invoice created, invoice paid, order
confirmation, welcome, ticket replies and more). Edit their wording under
**Configuration → Email Templates**.

## Where messages are logged

Every email PNLCS sends is also recorded, so customers can read their history
under the client portal's **Email History** page, and you can confirm what was
sent.
