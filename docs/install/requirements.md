# Requirements

| Component | Minimum |
|---|---|
| PHP | **8.4**. The locked dependencies require it: PHP 8.3 installs, then answers every request with a 500. |
| Database | MySQL 8.0, or MariaDB 10.6 or newer |
| Web server | Nginx or Apache, with PHP-FPM |
| Node.js | 18 or newer (20 LTS recommended), only to build the frontend assets |
| Composer | 2.x |
| Tools | `git`, `unzip`, `curl`, `cron` |
| Disk | About 130 MB for the application (code, PHP dependencies, built assets). Leave at least 2 GB free for the database, uploads and logs as they grow. `node_modules` (about 100 MB) is only needed while building. |
| Memory | 1 GB works for a small install; 2 GB is comfortable with the database on the same server. |

## PHP extensions

**Required**, and checked one by one by the install wizard:
`bcmath`, `curl`, `dom`, `fileinfo`, `gd`, `intl`, `mbstring`, `openssl`,
`pdo_mysql`, `tokenizer`, `xml`, `zip`.

**Optional:** `imap`, only for turning email in a mailbox into support tickets
([Support Mail Piping](../guides/support-mail-piping.md)). Everything else
works without it.

## Recommended

- An SMTP server or relay for sending email
  ([Configure Email](../guides/configure-email.md)).
- A TLS certificate (Let's Encrypt is fine) before you take payments: checkout
  and the admin login must not run over plain HTTP.

## Tested systems

The [installation guide](native.md) was run command by command on fresh
servers:

| System | PHP | Database |
|---|---|---|
| Ubuntu 24.04 LTS | 8.4 (ondrej PPA) | MySQL 8.0 |
| Debian 13 | 8.4 (Debian) | MariaDB 11.8 |
| AlmaLinux 9 (SELinux enforcing) | 8.4 (Remi) | MySQL 8.0 |

Rocky Linux 9 uses the same commands as AlmaLinux 9.
