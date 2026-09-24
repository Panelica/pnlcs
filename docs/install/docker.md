# Install with Docker

The official image [`panelica/pnlcs-runtime`](https://hub.docker.com/r/panelica/pnlcs-runtime)
carries PHP-FPM 8.4, nginx, Node.js 20 and supervisor. On its first start it
downloads the latest PNLCS code, installs the PHP dependencies and builds the
frontend, so there is nothing to build by hand.

## Start it

You need a database next to it. This starts MariaDB and PNLCS on a private
Docker network:

```bash
docker network create pnlcs-net

docker run -d --name pnlcs-db --network pnlcs-net \
  -v pnlcs_db:/var/lib/mysql \
  -e MYSQL_ROOT_PASSWORD=choose-a-root-password \
  -e MYSQL_DATABASE=pnlcs -e MYSQL_USER=pnlcs -e MYSQL_PASSWORD=choose-a-password \
  mariadb:11

docker run -d --name pnlcs --network pnlcs-net -p 8090:80 \
  -v pnlcs_app:/var/www/pnlcs \
  -e DB_HOST=pnlcs-db -e DB_DATABASE=pnlcs \
  -e DB_USERNAME=pnlcs -e DB_PASSWORD=choose-a-password \
  -e APP_URL=http://localhost:8090 \
  panelica/pnlcs-runtime:1.4
```

The two named volumes keep your data when a container is replaced:
`pnlcs_db` holds the database, `pnlcs_app` the application with its `.env`
and uploaded files (ticket attachments, logos, invoice PDFs).

The first start takes 3 to 5 minutes (Composer and the frontend build). Then
open **http://localhost:8090/install** and follow the
[install wizard](native.md#12-run-the-install-wizard). It checks the
requirements, creates the administrator with the username and password you
choose, and closes itself for good when it is done.

The container runs the scheduler (invoices, reminders, suspensions, backups)
every minute and keeps a queue worker running, both under supervisor: there is
no cron line to add.

## In production

- Put it behind a reverse proxy that terminates HTTPS, and set `APP_URL` to the
  public `https://` address.
- Keep the database on a volume, and back it up
  ([Backups](backups.md)).
- The image's own page lists every environment variable and production notes:
  [hub.docker.com/r/panelica/pnlcs-runtime](https://hub.docker.com/r/panelica/pnlcs-runtime).

## Update

```bash
docker exec pnlcs /usr/local/bin/update.sh
```

See [Updating](updating.md#docker) for what it does and the one error you may
meet.
