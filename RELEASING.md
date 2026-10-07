# Releasing PNLCS

How code gets from a merged pull request to the installations that run PNLCS,
and the rules every change and every release follows on the way. These rules
exist for one reason: an update must never harm an installation. Not its data,
not its customisations, not its uptime.

## The four promises

Every release, and the updater that applies it, keeps these:

1. **What belongs to the installation is never written to.** `.env`, `storage/`,
   the database's own data, a theme or module under its own name, its own hook
   files. The full list is the [user space contract](#the-user-space-contract).
2. **A file the operator changed is never overwritten silently.** A change to a
   core file is carried over to the new version when it merges cleanly, and the
   update stops and shows the file when it does not.
3. **An update is never left half done.** It ends either on the new version,
   working, or on the old version exactly as it was.
4. **On a conflict the operator decides.** The updater shows what clashes, file
   by file, and offers the choices. It never picks one.

## Versions

- `main` is where pull requests are merged. **No installation is ever updated
  from `main`.** Installations only ever receive tagged releases.
- Releases are numbered `MAJOR.MINOR.PATCH` and tagged `vMAJOR.MINOR.PATCH`.
  - **PATCH** (`1.4.2`): fixes only. No migration that changes the shape of
    existing data.
  - **MINOR** (`1.5.0`): features. Migrations only add (tables, columns, rows)
    and can be run twice without harm.
  - **MAJOR** (`2.0.0`): anything that breaks a module, theme or hook written
    against the previous version, or raises the minimum PHP. Announced at least
    one minor release earlier, with the old way still working in between.
- A beta is tagged `vX.Y.Z-beta.N` and published as a GitHub pre-release.
- The `VERSION` file on `main` holds the next version with `-dev`
  (`1.5.0-dev`). The release build writes the real number into the package.

## Channels and the release rhythm

| Channel | Gets | Who |
|---|---|---|
| `beta` | betas and stable releases | operators who opt in on **Setup → Updates** |
| `stable` | stable releases only | everyone else (the default) |

1. **Weekly beta.** When `main` has changed since the last beta, a beta is cut
   once a week.
2. **Ring 0 first.** Every beta is applied to our own installations before it
   is published to the beta channel.
3. **Promotion.** A beta that has run for **at least 7 days** on ring 0 and the
   beta channel without a regression becomes the stable release, **from the same
   commit**. If anything had to change, that is a new beta and the 7 days start
   again.
4. **Security fixes** go out as a patch release as soon as they pass the gates.
   The waiting time may be shortened; the gates may not.
5. **Withdrawal.** A release found to be harmful is turned back into a draft on
   GitHub. Updaters no longer see it, and the next release supersedes it.
6. **Major releases are never applied automatically**, on any channel.

## Gates: what every release must pass

A release is not published until all of these hold. No exceptions for "small"
releases.

- [ ] The full test suite is green.
- [ ] The language parity tests are green: every new string exists in every
      shipped language.
- [ ] The update lab is green: every supported starting version updates to this
      one, with the customisation scenarios, on Docker and on a native install
      ([tools/update-lab](tools/update-lab/README.md)).
- [ ] `CHANGELOG.md` has the entry, in plain English, with the sections that
      apply: *Database changes*, *For theme and module authors*, and *Views a
      theme may override that changed* (the updater lists the last one per
      installation too).
- [ ] The package is built by `tools/release/build-package.sh` from the tag,
      and signed. Nobody builds a release by hand.

## Rules for changes that ship

These are checked in review, for our own changes and contributed ones alike.

### Migrations

- Additive, and safe to run twice. Every migration has a working `down()`.
- **Never change data the operator changed.** Seeded rows are updated only
  while they are still the shipped version: email templates only where
  `custom` is false, settings only when they are unset, translations only in
  the files (a database row is the operator's).
- A destructive change (dropping a column, renaming a table) is split over two
  releases: the first stops using it, the next removes it.
- Long-running migrations (a table rewrite) are named in the changelog with an
  estimate.

### The user space contract

A release never adds, changes or removes anything here:

| Path | What it is |
|---|---|
| `.env` | the installation's configuration |
| `storage/` | uploads, backups, logs, sessions, the updater's own state |
| `public/storage` | the link to uploaded files |
| `themes/<slug>/` for a slug PNLCS does not ship | the operator's theme |
| `modules/<Type>/<Name>/` for a name PNLCS does not ship | the operator's or a vendor's module |
| `app/Hooks/<file>` for a file PNLCS does not ship | the operator's hooks |
| the database's rows | everything the operator entered or edited |

- **New built-in themes, modules and hook files take the `pnlcs-` prefix**, so a
  new built-in can never land on a name an operator already uses. (The updater
  checks anyway and stops on a collision.)
- Anything we tell operators to edit by hand must move into the admin area.
  A file people need to change is a file updates will clash on.
- **Security fixes are kept out of files operators commonly change** whenever
  possible, so a conflict there cannot hold a security fix back.

### Modules, themes and hooks

- The module interfaces, hook names and their parameters, and theme view
  names are a public API. Changing them is a MAJOR change, preceded by a
  deprecation in a MINOR release.
- A module or theme states the PNLCS versions it works with in its manifest
  (`"requires": {"pnlcs": ">=1.5 <2"}`). The updater will not update past a
  version an active module or theme says it does not support without the
  operator choosing to.
- Changing the variables a view receives is a change for every theme that
  overrides that view: it goes in the changelog section for theme authors.

## Pull request review

What a maintainer does with every pull request, ours and contributed alike.

1. **Read the whole diff.** Every file, every line. Look for what it changes
   besides what the title says. Unrelated changes are asked to be split into
   their own pull requests.
2. **Run the PR's own tests**, then the same tests on `main` **without** the
   change: a test that also passes on `main` proves nothing. A change without
   a test gets one before it is merged.
3. **Run the full suite on the merge result**, not on the branch alone.
4. **Five languages.** Every new string exists in `en`, `tr`, `de`, `pl` and
   `zh`, in the same key order as English. No text is written into a view.
5. **Check it against these rules**: the migrations rules, the user space
   contract, the public API of modules, themes and hooks, and the payment code
   (anything that moves money is reviewed line by line).
6. **Fix small things on top, don't bounce them.** A maintainer may add a
   follow-up commit (tests, translations, an edge case) on top of the merge,
   and says so in the thank-you comment.
7. **Merge with `--no-ff`**, so the contributor's commits keep their name, and
   only after checking that the PR's head has not moved since it was tested.
8. **Thank the contributor**, and say what was added on top and why.
9. **Merging does not ship.** The change reaches installations with the next
   release, through the gates above.

## Cutting a release (maintainers)

1. Pick the version by the rules above. Update `CHANGELOG.md`.
2. Tag the commit: `git tag -a vX.Y.Z-beta.N`.
3. Build and sign: `tools/release/build-package.sh vX.Y.Z-beta.N`.
4. Run the update lab against the package. All scenarios green.
5. Apply it to ring 0 with the updater itself, never by hand.
6. Publish the GitHub (pre-)release with the package, its release statement
   and signature.
7. Seven days later, without a regression: tag the same commit `vX.Y.Z`, build,
   sign, run the lab, publish.

Publishing a release, a Docker image or a tag is always an explicit decision of
a maintainer, made for that release.

## The Docker image

`panelica/pnlcs-runtime` is versioned on its own (`1.5`, `1.6`). A release
package says which image version it needs; the updater inside an older image
says which image to pull instead of updating.
