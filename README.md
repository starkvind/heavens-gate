# Heaven's Gate

Heaven's Gate is the live PHP web application used to publish and maintain the Heaven's Gate RPG setting: characters, chronicles, seasons, chapters, timeline events, organizations, groups, rules, systems, maps, documents, soundtrack, tools and minigames.

The project is intentionally database-driven. Public URLs use readable slugs while the editorial backend under `/talim` maintains the relational data behind them.

## Runtime at a glance

- PHP application with a single public front controller: `index.php`.
- Friendly routing in `app/bootstrap/request_router.php`.
- Controller dispatch in `app/bootstrap/body_work.php`.
- MySQL/MariaDB through `mysqli`.
- Production snapshot reviewed on 2026-09-02: MariaDB 10.5.29.
- 119 production tables, 4 views and 1 stored procedure in the 2026-09-01 snapshot.
- Desktop and mobile presentation layers share the same underlying content.
- Administrative maintenance lives under `/talim`.

## Repository map

| Path | Purpose |
|---|---|
| `app/bootstrap/` | Request bootstrapping, routing handoff and page dispatch. |
| `app/controllers/` | Public, admin and tool controllers. |
| `app/helpers/` | Shared database, security, routing and domain helpers. |
| `app/modules/` | Larger domain modules such as the card game. |
| `app/mobile/` | Mobile routing and presentation layer. |
| `app/partials/` | Shared layout fragments. |
| `api/` | JSON endpoints. |
| `assets/` | CSS, JavaScript and vendored frontend assets. |
| `public/` | Public images and sounds. |
| `tools/` | Repository-level CLI/developer tools. |
| `sql/` | Focused SQL audits; not a schema installer. |
| `reports/` | Dated editorial reports. |
| `admin_docs/` | Maintained technical documentation and historical migration notes. |

## Local/runtime requirements

The current runtime expects:

- PHP 8.x;
- `mysqli`;
- MariaDB/MySQL compatible with the production schema;
- a web server serving the repository root;
- `config.env` with:
  - `MYSQL_HOST`
  - `MYSQL_USER`
  - `MYSQL_PWD`
  - `MYSQL_BDD`

`app/helpers/db_connection.php` searches `config.env` outside the project root first, then in the root, then in the legacy `app/` location.

There is **no current full-schema installer in this repository**. Old documentation that referred to `install_schema_from_dump.php` or `schema_definition.php` is obsolete. The current production structure is documented from the continuity snapshot instead.

## Routing

A normal request flows through:

`.htaccess` → `index.php` → `request_router.php` → `body_work.php` → controller.

Do not expose PHP files under `app/` directly. `.htaccess` deliberately blocks `/app` and `/admin_docs`.

For a new simple public section, use:

~~~bash
python tools/scaffold_section.py --route-key example --slug example --title "Example" --dry-run
~~~

See [PUBLIC_SECTION_GUIDE.md](./admin_docs/PUBLIC_SECTION_GUIDE.md).

## Database

The authoritative reference used for this documentation refresh is the production snapshot:

`starkvind/heavens-gate-continuity/snapshots/web/database/production-2026-09-01.sql`

The snapshot contains:

- 43 `dim_*` tables;
- 36 `fact_*` tables;
- 39 `bridge_*` tables;
- 1 admin/migration backup table;
- 4 views;
- 1 stored procedure.

See [DATABASE_SCHEMA.md](./admin_docs/DATABASE_SCHEMA.md) for the maintained map.

Do not use `admin_docs/bdd_structure.txt` as the current schema. It is a retained historical snapshot because migration manifests still reference it.

## Maintenance

Start here:

- [Technical architecture](./admin_docs/TECHNICAL_DOCUMENTATION.md)
- [Database schema](./admin_docs/DATABASE_SCHEMA.md)
- [Scripts and maintenance](./admin_docs/SCRIPTS_AND_MAINTENANCE.md)
- [Admin module guide](./admin_docs/ADMIN_MODULE_GUIDE.md)
- [Public section guide](./admin_docs/PUBLIC_SECTION_GUIDE.md)
- [Card game](./admin_docs/CARD_GAME.md)
- [Card game skills](./admin_docs/CARD_GAME_SKILLS.md)
- [Documentation index](./admin_docs/README.md)

Historical migration manifests under `admin_docs/migration_manifest_*` remain useful as dated records, but they are not runtime documentation.

## Admin

The private backend is available through `/talim`. It covers, among other areas:

- characters and players;
- chronicles, realities, seasons and chapters;
- organizations, groups, affiliations and relationships;
- timeline events;
- maps, gallery, documents and links;
- systems, resources, traits, forms and powers;
- soundtrack;
- card-game catalog;
- database inspection and editorial audits.

Prefer the admin UI or a purpose-built tool over ad-hoc production SQL when the operation is already supported.

## License

Personal / non-commercial project codebase and campaign content.

Third-party universe references remain property of their respective owners.

