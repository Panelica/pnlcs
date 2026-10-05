# Support Mail Piping

Let customers open and reply to tickets **by email** — they email your support
address and it becomes a ticket automatically, and their email replies land on
the right ticket.

## How it works

PNLCS checks a mailbox on a schedule (every 5 minutes). For each new message:

- A reply whose subject contains `[Ticket #123456]` is added to that ticket.
- Anything else opens a **new ticket**.

Outgoing ticket emails already carry the `[Ticket #TID]` marker, so customer
replies thread correctly.

## Set it up

**Setup → Ticket Departments → (edit a department) → Mail Import**

Fill in the mailbox this department should read:

| Field | Example |
|-------|---------|
| Protocol | IMAP (recommended) or POP3 |
| Host | your mail provider's IMAP or POP3 server |
| Port | `993` (IMAP SSL) / `995` (POP3 SSL) |
| Encryption | SSL or STARTTLS |
| Username | the support mailbox, for example `support@example.com` |
| Password | the mailbox password |
| Folder | `INBOX` |

Options:

- **Delete after import** — remove messages from the mailbox once processed
  (recommended for POP3).
- **Accept unknown senders** — create tickets from people who don't yet have a
  client account (useful for presales). Off by default.

Enable **mail import** for the department and save.

## Safety built in

- **Auto-replies and bounces** (out-of-office, mailer-daemon, no-reply) are
  ignored, so you never get a ticket loop.
- The department's own address is skipped.
- A message that fails to process is left in the mailbox and retried next run.

## Requirements

- A dedicated support mailbox you can connect to over IMAP/POP3.
- PHP's `imap` extension. The Docker image has it; on your own server install
  `php8.4-imap` (Ubuntu, ondrej PPA) or `php-imap` (AlmaLinux/Rocky, Remi).
  Debian 13 does not package it. Without it the import logs
  `the PHP imap extension is not installed` and does nothing; the rest of
  PNLCS does not need it.

## Tickets without an account, and deleting tickets

Tickets do not only come from signed-in customers. The public **Contact Us**
form opens a ticket for anybody who fills it in, and mail import does the same
for unknown senders when you allow them. These tickets have no client account
behind them, so they are also where spam collects.

- **Support → Tickets → Only tickets without an account** lists just those.
- Tick tickets in the list (or the box in the header for the whole page) and
  press **Delete selected**. A single ticket has a **Delete** button on its
  page.
- Deleting removes the ticket with its replies, notes and attachments. It
  cannot be undone, and it needs the **manage tickets** permission.

To keep spam out in the first place, switch on reCAPTCHA for the contact form
and the ticket form (**Setup → General Settings → reCAPTCHA**) and fill in the
**Ticket Spam Filter**.
