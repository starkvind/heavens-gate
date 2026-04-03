# Heaven's Gate 5.0

Heaven's Gate is a PHP + MySQL narrative platform used to manage and publish a live RPG campaign: characters, chronicles, chapters, rules, systems, powers, timeline events, maps, gallery, soundtrack, and editorial/admin workflows.

This repository is the live production codebase and now documents the 5.0 maintenance and hardening cycle.

## 1. 5.0 Snapshot

Version 5.0 is a major operational update focused on security, data integrity, admin usability, and consistency between the public site, the admin backend, and the current schema dump.

Main outcomes:

- hardened admin authentication, sessions, CSRF handling, and password storage;
- introduced shared safe-response helpers for public runtime and bootstrap failures;
- cleaned bootstrap/runtime includes, UTF-8 output, and layout rendering;
- completed the timeline/events 5.0 flow with public list/detail pages and admin maintenance;
- added full admin CRUD modules for chronicles, realities, and players;
- rebuilt the soundtrack admin/catalog + link management workflow;
- formalized `pretty_id` handling for public canonical URLs and admin editing;
- aligned installation tooling and schema hardening with the current dump snapshot.

## 2. Stack

- PHP 8.0+ (`mysqli`, `openssl`; PHP 8.2 validated in this cycle)
- MySQL / MariaDB with `utf8mb4`
- Apache-style rewrite rules via [`.htaccess`](./.htaccess)
- include-based routing from [`index.php`](./index.php) and [`app/bootstrap/body_work.php`](./app/bootstrap/body_work.php)
- local frontend/vendor assets under [`assets/`](./assets)
- no Composer or npm required for runtime

## 3. Runtime Requirements

- Web server serving the repository root
- `mod_rewrite` enabled if Apache is used
- MySQL/MariaDB database using `utf8mb4`
- valid `config.env`
- write permissions for upload directories when admin image uploads are used:
  - `public/img/characters`
  - `public/img/player`
  - `public/img/chronicles`
  - other image folders already present under `public/img/`

## 4. Configuration

Database credentials are loaded by [`app/helpers/db_connection.php`](./app/helpers/db_connection.php).

Expected keys:

```ini
MYSQL_HOST=127.0.0.1
MYSQL_USER=your_user
MYSQL_PWD=your_password
MYSQL_BDD=your_database
ENCRYPTION_KEY=your_long_random_secret
```

Current config resolution order:

1. parent directory above the repository root
2. repository root
3. legacy fallback under `app/`

Notes:

- prefer storing `config.env` outside the document root whenever possible;
- `ENCRYPTION_KEY` is still required for legacy reversible-secret compatibility and password migration helpers;
- new or repaired admin passwords are stored hashed with `password_hash()`, not as reversible values.

## 5. Entry Points and Routing

Core entry points:

- public front controller: [`index.php`](./index.php)
- route dispatcher: [`app/bootstrap/body_work.php`](./app/bootstrap/body_work.php)
- web rewrite rules: [`.htaccess`](./.htaccess)
- admin backend: `/talim`

Selected canonical routes:

- `/timeline` -> `index.php?p=timeline` -> `app/controllers/main/events_main.php`
- `/timeline/event/<pretty_id>` -> `index.php?p=timeline_event&t=<pretty_id>` -> `app/controllers/main/events_page.php`
- `/players` -> `index.php?p=players` -> `app/controllers/playr/playr_list.php`
- `/players/<pretty_id>` -> `index.php?p=seeplayer&b=<pretty_id>` -> `app/controllers/playr/playr_page.php`
- `/chronicles/<pretty_id>` -> `index.php?p=chronicles&t=<pretty_id>`
- `/music` -> `index.php?p=ost` -> `app/controllers/ost/bso_main.php`
- `/maps/api` -> `index.php?p=maps_api`
- `/ajax/tooltip` -> `index.php?p=tooltip`
- `/ajax/mentions` -> `index.php?p=mentions`
- `/ajax/epis` -> `index.php?p=mentions&type=episode`

Canonical URL normalization is handled by [`app/helpers/pretty.php`](./app/helpers/pretty.php) together with the bootstrap route resolver.

## 6. Current Database Snapshot

Current working dump in the repository root:

- `dump-u807926597_hg-202604031114.sql`

Snapshot validated against the installer and current dump:

- 87 tables
- 33 `dim_*`
- 27 `fact_*`
- 27 `bridge_*`
- 3 simulator views:
  - `vw_sim_characters`
  - `vw_sim_forms`
  - `vw_sim_items`

Important schema points in the current dump:

- `dim_chronicles` includes `pretty_id`, `sort_order`, `image_url`, and descriptive text;
- `dim_players` includes `pretty_id`, `show_in_catalog`, `picture`, and `description`;
- `dim_realities` includes `pretty_id`, `description`, and `is_active`;
- `dim_seasons` currently includes `chronicle_id`;
- `fact_timeline_events` includes `pretty_id`, `date_precision`, `date_note`, `sort_date`, `event_type_id`, `is_active`, `location`, `source`, and legacy `timeline` text;
- timeline links are now represented through:
  - `bridge_timeline_events_characters`
  - `bridge_timeline_events_chapters`
  - `bridge_timeline_events_chronicles`
  - `bridge_timeline_events_realities`
- `dim_soundtracks` does not rely on `pretty_id` in the current dump;
- `bridge_soundtrack_links` currently includes:
  - unique triplet protection on `(soundtrack_id, object_type, object_id)`
  - lookup index on `(object_type, object_id)`

## 7. Main Functional Areas

- Core site: home, news, status, about, bibliography, search
- Narrative archive: chronicles, seasons, chapters, parties
- Character universe: characters, organizations, groups, worlds, players
- Rules and systems: traits, merits/flaws, maneuvers, systems, breeds, auspices, tribes, resources
- Powers: gifts, rites, totems, disciplines
- Media: maps, gallery, soundtrack
- Utilities: tooltip, mentions, dice, forum helpers, avatar builder, combat simulator
- Admin backend: `/talim?s=...`

## 8. Major 5.0 Change Areas

### 8.1 Admin hardening

- new shared admin session/auth helper: [`app/helpers/admin_auth.php`](./app/helpers/admin_auth.php)
- stricter session cookie settings (`httponly`, `SameSite=Lax`, `secure` when applicable)
- centralized logout flow
- AJAX admin requests now fail with controlled JSON `403` responses when unauthorized
- legacy admin password values are migrated toward `password_hash()`

### 8.2 Safe public/runtime error handling

- shared public helper: [`app/helpers/public_response.php`](./app/helpers/public_response.php)
- shared runtime/bootstrap helper: [`app/helpers/runtime_response.php`](./app/helpers/runtime_response.php)
- public controllers now avoid leaking raw SQL or bootstrap failures to end users

### 8.3 Bootstrap/runtime cleanup

- [`index.php`](./index.php) now normalizes UTF-8 output and strips BOM artifacts more safely
- connection bootstrap is idempotent through [`app/helpers/db_connection.php`](./app/helpers/db_connection.php)
- head/body assembly was cleaned up in:
  - [`app/bootstrap/head_work.php`](./app/bootstrap/head_work.php)
  - [`app/bootstrap/body_work.php`](./app/bootstrap/body_work.php)
- `assets/js/hg-tooltip.js` is now loaded in a safer deferred flow

### 8.4 Timeline/events 5.0

- public timeline list page moved to [`app/controllers/main/events_main.php`](./app/controllers/main/events_main.php)
- public event detail page moved to [`app/controllers/main/events_page.php`](./app/controllers/main/events_page.php)
- event model now relies on `fact_timeline_events` plus dedicated bridge tables and event types
- quick birthday maintenance is available through `admin_birthdays_quick`

### 8.5 New admin modules

New full CRUD-style narrative modules:

- [`app/controllers/admin/admin_chronicles.php`](./app/controllers/admin/admin_chronicles.php)
- [`app/controllers/admin/admin_realities.php`](./app/controllers/admin/admin_realities.php)
- [`app/controllers/admin/admin_players.php`](./app/controllers/admin/admin_players.php)

Shared support helpers:

- [`app/helpers/admin_catalog_utils.php`](./app/helpers/admin_catalog_utils.php)
- [`app/helpers/admin_uploads.php`](./app/helpers/admin_uploads.php)

### 8.6 Soundtrack rework

- [`app/controllers/admin/admin_bso.php`](./app/controllers/admin/admin_bso.php) now behaves as a real soundtrack catalog/admin surface
- [`app/controllers/admin/admin_bso_link.php`](./app/controllers/admin/admin_bso_link.php) provides link audit and relation management
- public soundtrack page in [`app/controllers/ost/bso_main.php`](./app/controllers/ost/bso_main.php) normalizes YouTube links and uses safer embeds

## 9. Installation and Provisioning

Schema installer:

- [`app/tools/install_schema_from_dump.php`](./app/tools/install_schema_from_dump.php)

Typical usage:

```bash
php app/tools/install_schema_from_dump.php --database=hg
```

With explicit connection values:

```bash
php app/tools/install_schema_from_dump.php --host=127.0.0.1 --user=usuario --password=secreto --database=hg
```

Dry run:

```bash
php app/tools/install_schema_from_dump.php --database=hg --dry-run=1
```

Seed admin password on first install:

```bash
php app/tools/install_schema_from_dump.php --database=hg --admin-password="change-this-now"
```

The installer:

- resolves the latest `dump-*.sql` automatically when `--dump` is omitted;
- creates the target database if needed;
- recreates the current table set;
- recreates the simulator views;
- seeds safe `dim_web_configuration` values;
- stores `rel_pwd` hashed when `--admin-password` is provided.

Additional schema hardening helper:

- [`app/tools/phase7_schema_hardening_20260403.php`](./app/tools/phase7_schema_hardening_20260403.php)

Its scope is intentionally narrow and safe: soundtrack-link integrity and related supporting indexes/constraints.

## 10. Security and Deployment Notes

- [`.htaccess`](./.htaccess) blocks direct access to `app/`, SQL dumps, markdown docs, and sensitive config/runtime files;
- if you deploy behind Nginx or another server that ignores `.htaccess`, replicate those deny rules explicitly;
- do not import production `rel_pwd` into other environments unless you explicitly intend to preserve the admin secret;
- keep dumps, runtime secrets, and one-off migration helpers out of version control.

## 11. Reference Docs

- architecture and maintenance notes: [`TECHNICAL_DOCUMENTATION.md`](./TECHNICAL_DOCUMENTATION.md)
- backend mapping for bot/integration work: `TELEGRAM_BOT_BACKEND_GUIDE.md`

## 12. License

Personal / non-commercial project codebase and campaign content.
Third-party universe references remain property of their respective owners.
