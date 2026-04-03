# Heaven's Gate

Heaven's Gate is a live PHP web platform for publishing and maintaining a narrative RPG universe. The site brings together character sheets, chronicles, chapters, rules, systems, maps, soundtrack, gallery content, and a private admin area used to keep the world up to date.

This `README` is intentionally user-facing. Backend architecture, schema details, routing, provisioning, and maintenance notes now live in [`TECHNICAL_DOCUMENTATION.md`](./TECHNICAL_DOCUMENTATION.md).

## What You Can Do On The Site

Public sections currently include:

- character browsing, profiles, organizations, groups, and realities;
- chronicle, season, chapter, and timeline navigation;
- rules, systems, powers, inventory, and supporting lore;
- maps, gallery, and soundtrack pages;
- utility tools such as mentions, dice helpers, forum helpers, and the combat simulator.

## Admin Utilities

The private admin area lives under `/talim` and is used to maintain the campaign without touching SQL directly.

Key admin capabilities include:

- character, group, player, chronicle, and reality management;
- season, chapter, timeline, and birthday maintenance;
- docs, links, maps, gallery, powers, systems, and soundtrack administration;
- schema inspection and schema initialization from the admin panel.

## 5.0 Highlights

Version 5.0 is a large maintenance and consolidation release. In practical terms, it means:

- safer admin authentication and session handling;
- cleaner public error handling;
- consolidated timeline/event flows;
- new CRUD modules for chronicles, realities, and players;
- a rebuilt soundtrack workflow;
- stronger schema integrity and better operational tooling.

## Running The Project

To run the site you need:

- PHP 8.x with `mysqli`;
- MySQL or MariaDB;
- a valid `config.env`;
- a web server serving the repository root.

If you only need the technical setup, provisioning flow, schema notes, or backend map, go straight to:

- [`TECHNICAL_DOCUMENTATION.md`](./TECHNICAL_DOCUMENTATION.md)
- `TELEGRAM_BOT_BACKEND_GUIDE.md`

## Documentation

- User/project overview: [`README.md`](./README.md)
- Technical architecture and maintenance: [`TECHNICAL_DOCUMENTATION.md`](./TECHNICAL_DOCUMENTATION.md)
- Backend/controller/schema map for bot or integration work: `TELEGRAM_BOT_BACKEND_GUIDE.md`

## License

Personal / non-commercial project codebase and campaign content.
Third-party universe references remain property of their respective owners.
