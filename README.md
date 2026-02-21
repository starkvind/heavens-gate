# Heaven's Gate - Technical README

Heaven's Gate is a PHP + MySQL narrative platform for campaign management and publishing (characters, systems, powers, chapters, maps, docs, soundtrack, admin tools).

This repository is the production codebase used by the project.

## 1. Stack

- PHP (procedural style, include-based routing)
- MySQL / MariaDB (schema with `dim_*`, `fact_*`, `bridge_*`)
- jQuery + selective local vendor libs in `assets/vendor/`
- No Composer or npm required for runtime

## 2. Runtime Requirements

- PHP 7.4+ (8.x recommended)
- MySQL/MariaDB with `utf8mb4`
- Web server serving repository root
- Write permissions for avatar uploads:
  - physical path: `public/img/characters`
  - public URL base: `/img/characters`

## 3. Configuration

Database credentials are read from:

- `config.env` (expected one level above project root from `app/helpers/heroes.php` resolution)

Required keys:

```ini
MYSQL_HOST=127.0.0.1
MYSQL_USER=your_user
MYSQL_PWD=your_password
MYSQL_BDD=your_database
```

Connection bootstrap:

- `index.php` includes `app/helpers/heroes.php`
- `heroes.php` loads `config.env`, opens `mysqli`, forces `utf8mb4`

## 4. Entry Point and Routing

- Main entry: `index.php`
- Router: `app/bootstrap/body_work.php`
- Main route param: `p`
- Additional params vary by module (`b`, `t`, `tc`, etc.)

Examples:

- `/` -> default news
- `/?p=bios` -> character list by type
- `/?p=muestrabio&b=123` -> character page
- `/talim?s=admin_pjs` -> admin characters CRUD

Pretty URLs are normalized in router helpers (`app/helpers/pretty.php`) and route handlers.

## 5. Main Functional Areas

- Main:
  - news, about, status, bibliography, search
- Characters:
  - lists, group/org pages, character page, relations nebula
- Documentation:
  - docs, traits, merits/flaws, archetypes, maneuvers
- Powers:
  - gifts, rites, totems, disciplines
- Systems:
  - systems, breeds/auspices/tribes/misc details, forms
- Campaign:
  - seasons, chapters, timeline, active parties
- Media:
  - gallery, maps, soundtrack
- Tools:
  - dice roller, mentions, tooltip, forum snippets
- Admin:
  - full management panel under `/talim?s=...`

## 6. Admin Panel Highlights

Admin root:

- `/talim` (loads `app/controllers/admin/admin_main.php`)

Relevant modules:

- `admin_pjs` - full characters CRUD
- `admin_avatar_mass` - bulk avatar upload (AJAX, no pagination)
- `admin_bridges` - membership bridges editor
- `admin_relations` - character relations
- `admin_powers`, `admin_docs`, `admin_systems`, `admin_traits`, etc.

### Bulk avatar manager

Route:

- `/talim?s=admin_avatar_mass`

Features:

- Long list for fast manual batch operations
- Filters by Group, Organization, Chronicle
- Per-row upload without refresh (AJAX)
- Uses same validation/storage approach as `admin_pjs`

## 7. Data Model Convention

The DB uses a hybrid star-like/domain model:

- `dim_*`: catalogs/dimensions
- `fact_*`: content/transactions/entities
- `bridge_*`: many-to-many links and state bridges

Core entity:

- `fact_characters`

Most character state is now normalized in bridge tables (`traits`, `resources`, memberships, powers, items, merits/flaws, relations).

## 8. Documentation Files

- Technical DB and architecture documentation:
  - `TECHNICAL_DOCUMENTATION.md`
- Raw database inventory:
  - `bdd_structure.txt`

## 9. Development Notes

- Prefer prepared statements for SQL.
- Keep UTF-8 clean (`utf8mb4` end-to-end).
- Keep third-party assets local in `assets/vendor` (avoid remote CDN dependencies where possible).
- Avoid hardcoded system labels when table-driven alternatives exist.
- Do not introduce destructive migrations without backup.

## 10. Troubleshooting

- If AJAX endpoints return "invalid JSON":
  - check PHP warnings/notices before JSON output
  - verify no BOM in PHP files
  - verify endpoint mode (`ajax=1`) is routed correctly
- If avatars upload but do not display:
  - verify file exists in `public/img/characters`
  - verify URL path and web server aliasing
  - check `fact_characters.image_url` value

## 11. License

Personal/non-commercial project codebase and campaign content.
Third-party universe references remain property of their respective owners