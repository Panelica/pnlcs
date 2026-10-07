# Update lab

Proves, before a release is published, that updating to it keeps every promise
in [RELEASING.md](../../RELEASING.md): the operator's files and changes are
kept, a conflict stops the update, and an update that fails - at any step,
even with the process killed - leaves the installation exactly as it was.

Nothing here is published or shipped (`/tools` is left out of release
packages), and nothing leaves the machine: releases are signed with a lab key
generated in `.work/`, served from `file://` links.

```bash
tools/update-lab/run-all.sh          # build the lab releases, run every scenario
tools/update-lab/run-all.sh --no-build
```

Needs PHP 8.4+, composer, npm, git, Docker, and a MySQL the scenarios can
create databases in (`LAB_DB_HOST`, `LAB_DB_PORT`, `LAB_DB_USER`,
`LAB_DB_PASS`; default `127.0.0.1:33061`, `root`/`testroot`).

## What it builds

`make-releases.sh` builds, from `HEAD`, with `tools/release/build-package.sh`:

| Release | Content |
|---|---|
| A `1.3.0` | `HEAD` as it is |
| B `1.3.1` good | `HEAD` + `synthetic-change.sh`: changes `public/robots.txt` where the operator also writes, the bottom of the admin layout (the operator edits its top), the footer view (the operator's theme overrides it); adds a file and a migration, removes a file |
| B `1.3.1` bad-migration | the same, with a migration that creates a table and then fails |
| B `1.3.1` bad-view | the same, with the admin login page throwing when rendered |

## The scenarios

Every installation is customised by `customise.php` the way an operator's
would be: their own theme (overriding a view), module and hook file, an upload,
a line in `.env`, an email template and a translation edited in the database,
and two edits to core files - one the next release also makes (a conflict),
one it does not (a clean merge). `snapshot.php` fingerprints every file and
every table (`CHECKSUM TABLE`); `compare.php` compares two fingerprints.

| Scenario | What must hold |
|---|---|
| `scenario-native.sh good` | the check finds the conflict and changes nothing; the update refuses until it is decided; with "keep mine" it updates, every operator file is byte for byte the same, the edit to the admin layout is merged, the migration ran, the operator's template and translation are untouched, the site is healthy |
| `scenario-native.sh bad-migration` | the update fails and is rolled back: every file and every table as before, the table the failed migration created gone, the site up |
| `scenario-native.sh bad-view` | the health check after the update catches the broken page; rolled back as above |
| `scenario-native.sh crash` | the process dies during the migrations; every later update refuses and says why; `pnlcs:update-rollback` puts every file and table back |
| `scenario-docker.sh ...` | the same four, inside the 1.5 image: the first start installs the signed release (no git clone), `update.sh` updates, nginx and PHP-FPM serve the new version; after a crash, restarting the container rolls back by itself |
| `scenario-legacy.sh native` | an installation cloned with git before releases existed is compared with its commit, its changes kept, and becomes a release installation (`.git` left alone) |
| `scenario-legacy.sh docker` | a container set up by image 1.4 (a clone of main), recreated on image 1.5 with the same volume: nothing is wiped or re-cloned, and the update keeps every change |
| `scenario-ui.sh` | the admin-area path in the 1.5 image, driven over HTTP as a browser would: Setup -> Updates, check (run by the container's scheduler), the conflict shown, the merged file downloaded, "keep mine", update, the new version and the history shown |
| `scenario-vm.sh` | a real server set up by `docs/install/native.md` (nginx, PHP-FPM, MariaDB, cron; `LAB_VM_IP`, `LAB_VM_PASS`): the command-line update; a failing release requested from Setup -> Updates and run by cron, rolled back; a crash, with the site in maintenance until `pnlcs:update-rollback` |

A difference the running application makes by itself while a scenario waits
for the scheduler - the activity log, the scheduler's own run log and its
`LastCronRun` heartbeat - is left out of the comparison by name
(`compare.php --ignore-table`, `--ignore-row`); everything else must match.

