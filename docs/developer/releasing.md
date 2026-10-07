# Releases and updates

How PNLCS reaches installations, and what every release promises them. The
full rules - versions, the weekly rhythm, the gates a release passes, how
pull requests are reviewed - are in
[RELEASING.md](https://github.com/Panelica/pnlcs/blob/main/RELEASING.md).

## The four promises

1. What belongs to an installation is never written to: `.env`, `storage/`,
   the database's own data, a theme or module under its own name, its own
   hook files.
2. A file the operator changed is never overwritten silently: the change is
   merged with the new version, or the update stops and shows the file.
3. An update is never left half done: it ends on the new version, working, or
   on the old one exactly as it was.
4. On a conflict, the operator decides.

## Versions and channels

- Releases are `MAJOR.MINOR.PATCH`, betas `MAJOR.MINOR.PATCH-beta.N`.
  Installations only ever receive tagged releases, never `main`.
- **Stable** gets stable releases; **beta** gets betas too, about a week
  earlier. A beta becomes stable, from the same commit, after at least seven
  days without a regression.
- A new **major** version (`2.0`) may break modules, themes or hooks written
  for the previous one; it is announced a minor release ahead and never
  applied automatically.

## For module and theme authors

- Keep everything in your own folder (`modules/<Type>/<Name>`,
  `themes/<slug>`): updates never touch it.
- Say which versions you support: `"requires": {"pnlcs": ">=1.3 <2"}` in
  `pnlcs.json` or `theme.json`. An update outside that range waits until the
  operator updates your module or theme.
- Replace as few core views as you can. The changelog lists, per release, the
  views whose data changed (*Views a theme may override that changed*), and
  **Setup → Updates** shows each operator which of their theme's views a
  release changes.

## The update lab

Every release is applied, before it is published, to installations that look
lived in - their own theme and module, edits to core files, a custom email
template - on Docker and on a native install, and to releases built to fail
(a broken migration, a broken page, a process killed half way). The lab and
its scenarios are in
[tools/update-lab](https://github.com/Panelica/pnlcs/tree/main/tools/update-lab).
