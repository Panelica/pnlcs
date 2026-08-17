# App catalogue logos

Logos for the Docker app catalogue, committed so a fresh install opens on a
finished-looking grid instead of a wall of letter tiles.

`manifest.json` maps a panel template slug to a file in this directory. The
catalogue layers three sources, in this order:

1. an image the operator uploaded (stored under `storage/app/public/docker-apps`)
2. the file listed here
3. a coloured letter tile drawn from the slug

Refresh or extend the set with `php artisan docker-apps:import-logos`, which
fetches into layer 1 and leaves this directory alone.

The logos are the trademarks of their respective projects and are included to
identify the applications they belong to. Remove any file here if you would
rather not ship it; the catalogue falls back to a letter tile.
