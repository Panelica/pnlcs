# Configure Email

Email is **required**. Invoices, order confirmations, password resets, welcome
messages and ticket replies all go out by email.

## Set up sending

**Setup → General Settings → Mail Configuration**

| Field | What to enter |
|---|---|
| **Enable outgoing emails** | On. Off stops every email PNLCS sends; the log then records `Outgoing mail suppressed: mail is disabled in the panel settings.` |
| **Mail type** | **SMTP** (recommended), or **PHP mail** to hand mail to the server's own `sendmail`. |
| **System email address** | The sender address, for example `noreply@example.com`. |
| **Email from name** | The sender name customers see, for example your company name. |
| **SMTP host** | Your mail provider's SMTP server. |
| **SMTP port** | `587` for STARTTLS, `465` for SSL. |
| **SMTP encryption** | `TLS` for port 587, `SSL` for 465. |
| **SMTP username / password** | From your mail provider. Left empty on a later save, the stored password is kept. |

Save, then press **Send Test Email** and check that it arrives, spam folder
included. The test goes through exactly the same settings as real mail.

!!! note "The panel wins over `.env`"
    Once a mail type is chosen here, these settings replace the `MAIL_*` values
    in `.env`. Until then `.env` decides, and a fresh install's `.env` has
    `MAIL_MAILER=log`: mail is written to the log, not sent.

## Keep it out of spam folders

Set these DNS records for the domain you send from:

- **SPF**: allows your mail server to send for the domain.
- **DKIM**: signs outgoing mail (your mail provider gives you the record).
- **DMARC**: a policy that ties SPF and DKIM together.

A transactional mail service (Amazon SES, Postmark, Mailgun, SendGrid and the
like) usually reaches the inbox more reliably than a self-hosted SMTP server.

## Email templates

PNLCS ships ready-made templates: invoice created, payment confirmation,
reminders, order confirmation, service welcome, suspension, domain renewal and
more. Change their wording under **Setup → Email Templates**.

## What was sent

Every email PNLCS sends to a customer is recorded. Customers read theirs under
**Email History** in the client area; staff see them on the client's record.

## Not arriving?

See [Emails are not being sent](../troubleshooting/common-issues.md#emails-are-not-being-sent).
