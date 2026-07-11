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

**Configuration → Support Departments → (edit a department) → Mail Import**

Fill in the mailbox this department should read:

| Field | Example |
|-------|---------|
| Protocol | IMAP (recommended) or POP3 |
| Host | `mail.your-domain.com` |
| Port | `993` (IMAP SSL) / `995` (POP3 SSL) |
| Encryption | SSL or STARTTLS |
| Username | `support@your-domain.com` |
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
- The `imap` PHP extension (included in the official Docker image; install it
  on your server if you set PNLCS up manually).
