# Heaven's Gate - Technical README

Heaven's Gate is a PHP + MySQL narrative platform used to manage and publish a live RPG campaign (characters, chronicles, chapters, systems, powers, timeline events, maps, gallery, soundtrack, and admin workflows).

This repository is the production codebase.

## 1. Stack

- PHP (procedural, include-based routing)
- MySQL / MariaDB (`dim_*`, `fact_*`, `bridge_*` schema style)
- jQuery + local vendor assets (`assets/vendor/`)
- No Composer or npm required for runtime

## 2. Runtime Requirements

- PHP 7.4+ (PHP 8.x recommended)
- MySQL / MariaDB with `utf8mb4`
- Web server serving repository root
- `mod_rewrite` enabled if Apache is used
- Write permissions for avatar uploads:
  - physical path: `public/img/characters`
  - public URL base: `/img/characters`

## 3. Configuration

Database credentials are loaded from `config.env` (resolved by `app/helpers/db_connection.php`).

Required keys:

```ini
MYSQL_HOST=127.0.0.1
MYSQL_USER=your_user
MYSQL_PWD=your_password
MYSQL_BDD=your_database
ENCRYPTION_KEY=your_long_random_secret
```

Notes:

- `ENCRYPTION_KEY` is required for admin authentication because `/talim` reads and decrypts `rel_pwd` from `dim_web_configuration`.
- Runtime now accepts `config.env` in either:
  - the parent directory above the repository root;
  - the repository root;
  - a legacy fallback under `app/`.
- For consistency, prefer placing `config.env` outside the web root or one level above the repository root.

Bootstrap:

- `index.php` -> `app/helpers/db_connection.php`
- `db_connection.php` loads env vars, opens `mysqli`, enforces `utf8mb4`

## 4. Routing and Entry Point

- Main entry: `index.php`
- Route map: `app/bootstrap/body_work.php`
- Main route param: `p`
- Pretty URL normalization and canonical redirect logic: `app/bootstrap/body_work.php` + `app/helpers/pretty.php`

Examples:

- `/` -> welcome landing page
- `/home` -> welcome landing page
- `/seasons` -> seasons archive landing page
- `/seasons/complete` -> complete seasons
- `/seasons/interludes` -> interludes / incisos
- `/seasons/personal-stories` -> personal stories
- `/seasons/specials` -> specials
- `/chapters` -> global episode table
- `/?p=bios` -> character list
- `/?p=muestrabio&b=123` -> character page
- `/timeline` -> timeline events main page
- `/timeline/event/<pretty_id>` -> timeline event detail page
- `/talim?s=admin_timelines` -> timeline admin

## 5. Main Functional Areas

- Main: home, news, about, status, bibliography, search
- Characters: lists, groups/organizations, character pages, relation maps
- Rules and docs: documents, traits, merits/flaws, archetypes, maneuvers
- Powers: gifts, rites, totems, disciplines
- Systems: system pages, forms, breed/auspice/tribe details
- Campaign: seasons, chapters, active parties, timeline events
- Media: maps, gallery, soundtrack
- Tools: dice, tooltip, mentions, snippets
- Admin: `/talim?s=...`

## 6. Operation Events 5.0 (Recent Major Update)

The timeline/event domain was fully refactored.

### 6.1 Frontend

- Old `main_timeline.php` flow was replaced by:
  - `app/controllers/main/events_main.php`
  - `app/controllers/main/events_page.php`
- Routes wired in `app/bootstrap/body_work.php`:
  - `p=timeline`
  - `p=timeline_event`
- Timeline view now uses Apache ECharts (local vendor asset in `assets/vendor/echarts/`)
- List view integrates DataTables when available
- Event detail page includes related characters, chapters, chronicles (and hidden realities toggle for spoiler control)

### 6.2 Admin

- `admin_timelines.php` was upgraded for the new event model:
  - event type catalog
  - relations to characters / chapters / chronicles / realities
  - AJAX CRUD with CSRF and bridge sync
- New module: `admin_birthdays_quick.php`
  - route: `/talim?s=admin_birthdays_quick`
  - fast audit/fix for birthdays and linked birth events

### 6.3 Database model changes

Core entities added/refined:

- `dim_timeline_events_types`
- `fact_timeline_events` (new columns and indexes: `pretty_id`, `date_precision`, `date_note`, `sort_date`, `event_type_id`, `is_active`)
- `bridge_timeline_events_characters`
- `bridge_timeline_events_chapters`
- `bridge_timeline_events_chronicles`
- `bridge_timeline_events_realities`

Legacy compatibility retained:

- `fact_timeline_events.kind` kept as LEGACY mirror of type slug
- `fact_timeline_events.timeline` kept as LEGACY text field
- `bridge_timeline_links` still exists but is legacy for this domain

### 6.4 Birthday migration strategy

- Birthdays are now modeled as timeline events of type `nacimiento`
- Character pages now resolve birthday from timeline event bridges (instead of only direct `fact_characters.birthdate_text`)
- Batch scripts exist for creation/fix text normalization

## 7. Data Model Convention

- `dim_*`: catalogs / dimensions
- `fact_*`: business entities / narrative facts
- `bridge_*`: many-to-many and state links

Main campaign hubs:

- `fact_characters`
- `fact_timeline_events` (post Events 5.0)

## 8. Technical Documentation

- Architecture and schema notes: `TECHNICAL_DOCUMENTATION.md`
- Backend map, routing and query inventory for bot/integration work:
  - `TELEGRAM_BOT_BACKEND_GUIDE.md`

## 9. Database Provisioning

- Schema installer: `app/tools/install_schema_from_dump.php`
- Source dump: `dump-u807926597_hg-202603282141.sql`

Typical usage:

```bash
php app/tools/install_schema_from_dump.php --host=127.0.0.1 --user=usuario --password=secreto --database=hg
```

If you want admin access enabled on first boot:

```bash
php app/tools/install_schema_from_dump.php --database=hg --admin-password="change-this-now"
```

The installer:

- creates the database if needed;
- creates the current 87-table schema;
- recreates simulator views;
- seeds safe `dim_web_configuration` values;
- does not import production `rel_pwd` unless explicitly requested.

## 10. Development Notes

- Prefer prepared statements in controllers/services
- Keep encoding clean (`utf8mb4` end-to-end)
- Keep admin AJAX responses in strict JSON contract (`ok/message/data/errors/meta`)
- Avoid destructive migrations without validated backup/dump

## 11. Production Hardening

- Keep `config.env` out of the document root whenever possible.
- Do not restore `dim_web_configuration.rel_pwd` from production dumps into other environments.
- The current `.htaccess` blocks direct access to:
  - `app/`
  - markdown technical docs
  - SQL dumps
  - upgrade notes
- If you deploy behind Nginx or another server that ignores `.htaccess`, replicate those deny rules explicitly.

## 12. Troubleshooting

- If an AJAX endpoint returns invalid JSON:
  - check warnings/notices emitted before JSON output
  - verify BOM-free PHP files
  - verify route is called with `ajax=1`
- If event pages appear empty:
  - verify `fact_timeline_events` and timeline bridges exist
  - verify `event_type_id` references valid `dim_timeline_events_types` rows
  - verify events are active when filters enforce active-only

## 13. License

Personal / non-commercial project codebase and campaign content.
Third-party universe references remain property of their respective owners.
