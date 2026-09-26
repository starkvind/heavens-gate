# Heaven's Gate

Heaven's Gate is the production PHP application for publishing and maintaining the Heaven's Gate campaign archive.

The repository is intentionally a production repository: runtime code, permanent regression checks, operational documentation and a small set of reusable maintenance tools. Historical migrations, one-use SQL and refactor scaffolding belong in starkvind/heavens-gate-continuity, not here.

## Production architecture

- Single public front controller: index.php.
- Canonical routing: app/routing/.
- Dispatch/runtime HTTP layer: app/http/.
- Domain queries and data access: app/domains/.
- Public/Admin/tool controllers: app/controllers/.
- Desktop presentation: app/views/ and app/presentation/.
- Mobile compatibility presentation: app/mobile/.
- Shared helpers: app/helpers/.
- Frontend assets: assets/.
- Public media: public/.
- Operational CLI tools: tools/.
- Technical manuals: admin_docs/.

Normal request flow:

    .htaccess
      -> index.php
      -> app/routing/request_runtime.php
      -> app/routing/path_matcher.php or legacy_query.php
      -> app/http/page_dispatch.php
      -> app/routing/routes.php
      -> app/http/dispatch_policy.php
      -> app/http/dispatcher.php
      -> controller
      -> view/presentation

## Database

Production is MariaDB 10.5.x through mysqli.

Current documented production surface after the September 2026 cleanup:

- 36 dim_* tables;
- 27 fact_* tables;
- 38 bridge_* tables;
- 101 tables total;
- 0 views;
- 0 stored procedures.

There is no schema installer or one-use SQL directory in the production repository. Schema history and historical SQL live in continuity.

See [admin_docs/DATABASE_SCHEMA.md](./admin_docs/DATABASE_SCHEMA.md).

## Configuration

The runtime expects config.env with:

- MYSQL_HOST
- MYSQL_USER
- MYSQL_PWD
- MYSQL_BDD

app/helpers/db_connection.php checks the parent of the project root first, then the project root, then the legacy app location.

Never commit config.env or credentials.

## How to modify the site safely

Start with [admin_docs/manuals/README.md](./admin_docs/manuals/README.md).

It contains operational manuals for routing, public pages, Admin, database, CSS, JavaScript, mobile, PWA, tools/APIs, security, deployment, CI and the domain map.

Useful quick references:

- [Technical architecture](./admin_docs/TECHNICAL_DOCUMENTATION.md)
- [Route dictionary](./admin_docs/ROUTE_DICTIONARY.md)
- [Database schema](./admin_docs/DATABASE_SCHEMA.md)
- [Scripts and maintenance](./admin_docs/SCRIPTS_AND_MAINTENANCE.md)
- [Public section guide](./admin_docs/PUBLIC_SECTION_GUIDE.md)
- [Admin module guide](./admin_docs/ADMIN_MODULE_GUIDE.md)

## Production checks

Full live HTTP smoke:

    bash tools/production_smoke.sh

Read-only architecture inventory:

    python3 tools/architecture_inventory.py

For creating a simple public section, always inspect a dry-run first:

    python3 tools/scaffold_section.py --route-key example --slug example --title "Example" --dry-run

## CI

Two permanent workflows protect production:

- Project CI: PHP lint, security, private-tree guards, CSS ownership, cache/versioning, API auth and repository hygiene.
- Architecture Regression Checks: routing, request-state, query boundaries, mobile/PWA, performance, Admin structure and retired-feature guards.

Historical phase workflows are not part of production anymore.

## License

Personal / non-commercial project codebase and campaign content.

Third-party universe references remain property of their respective owners.
