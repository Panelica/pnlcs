# Backups

## The database, every night

PNLCS backs its database up by itself. The scheduled task `pnlcs:db-backup`
runs **every day at 04:30** (through the [cron runner](native.md#13-schedule-the-cron-runner),
or the scheduler inside the Docker container), writes a gzip file and keeps the
most recent ones.

| | |
|---|---|
| Where | `storage/app/backups/db/pnlcs-YYYYMMDD-HHMMSS.sql.gz` |
| How many are kept | 7, setting `db_backup_retention` |
| On or off | setting `db_backup_enabled` (on unless set to `0`) |

The dump uses `mysqldump` when the server has it, and dumps over the PHP
connection when it is missing or fails.

## Run one by hand

From the PNLCS directory, as the web server user:

```bash
php artisan pnlcs:db-backup                                   # the defaults above
php artisan pnlcs:db-backup --dir=/mnt/backups --retention=30  # another place, keep 30
php artisan pnlcs:db-backup --php                             # force the PHP dump
```

## Change the settings

`db_backup_retention` and `db_backup_enabled` have no screen yet. Set them with
the API ([setconfigurationvalue](../api/system.md#setconfigurationvalue)):

```bash
curl -X POST https://example.com/api/v1/setconfigurationvalue \
  -H "X-API-Key: $PNLCS_IDENTIFIER" \
  -H "X-API-Secret: $PNLCS_SECRET" \
  --data-urlencode "setting=db_backup_retention" \
  --data-urlencode "value=30"
```

## Restore

```bash
gunzip < storage/app/backups/db/pnlcs-20260101-043000.sql.gz | mysql -u pnlcs -p pnlcs
```

Restore into an empty database, or one you are ready to overwrite: the dump
replaces the tables it contains.

## What the nightly backup does not cover

The task covers the **database only**. Two more things belong in your
server-level backup:

- **`storage/`**: ticket attachments, uploaded logos and branding, generated
  invoice PDFs.
- **`.env`**: it holds `APP_KEY`, which encrypts stored secrets such as gateway
  and registrar keys. A database restored without the matching `APP_KEY`
  cannot read them.

Copy the `.sql.gz` files off the server too, or point `--dir` at mounted or
off-site storage: a backup on the same disk goes when the disk goes.
