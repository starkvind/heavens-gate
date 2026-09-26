# PHP Phase 9.4 — Installable PWA

## Status

**IMPLEMENTED — CI PASS — Raspberry/device smoke pending.**

Branch: `php-refactor`.

## Goal

Make Heaven's Gate installable as a Progressive Web App while retaining the dedicated mobile presentation as the installed-app shell.

The browser website remains canonical. The PWA is another presentation entrypoint over the same routes, data and application rules.

## Manifest

Canonical manifest:

`/manifest.json`

Legacy compatibility copy retained at:

`/manifest.webmanifest`
`/img/favicon/site.webmanifest`

The manifest defines:

- stable app id `/`;
- name `Heaven's Gate`;
- short name `HG`;
- scope `/`;
- `display: standalone`;
- start URL `/home?view=mobile`;
- existing 192px and 512px WebP app icons;
- shortcuts to Characters, Seasons and Search.

Desktop and mobile heads both reference the canonical manifest.

## Installed shell

Launching the installed app enters:

`/home?view=mobile`

This explicitly selects the supported mobile presentation and refreshes the existing `hg_view` preference.

No User-Agent routing was reintroduced.

## Install UX

Owned controller:

`assets/js/hg-pwa.js`

It:

- registers the root service worker;
- captures Chromium's `beforeinstallprompt`;
- exposes `Instalar Heaven's Gate` in the mobile menu when installation is available;
- hides install UI in standalone mode;
- reacts to `appinstalled`;
- provides iPhone/iPad instructions for Safari's Add to Home Screen flow.

The normal desktop frontend also registers the same service worker but does not add duplicate install UI.

## Service worker

Root worker:

`/service-worker.js`

Caching is deliberately conservative.

Navigations:

- network first;
- never stored as dynamic page cache;
- fall back to `/offline.html` only when the network is unavailable.

Static cache eligibility is restricted to:

- `/assets/`;
- `/img/favicon/`;
- canonical manifest;
- explicit branding icon.

Dynamic application pages, Admin, APIs, POSTs and user-generated data are not cached by the worker.

This preserves fresh PHP/data behavior online.

## Offline fallback

`/offline.html`

The fallback explains that dynamic Heaven's Gate data remains network-owned and offers a retry into the mobile shell.

## iOS/mobile metadata

The mobile shell now includes:

- canonical manifest;
- Apple standalone metadata;
- Apple touch icon;
- theme color;
- PWA install controller.

## Permanent guard

Added:

`.github/ci/php-phase9-pwa-audit.py`

Integrated into PHP Refactor Characterization together with JavaScript syntax checks for:

- `assets/js/hg-pwa.js`;
- `service-worker.js`.

The guard verifies:

- manifest validity;
- 192px/512px icons exist;
- PWA starts in the mobile shell;
- both public heads expose the canonical manifest/controller;
- install UI contracts exist;
- service-worker navigation remains network-first;
- offline fallback remains present;
- privileged/dynamic route markers are not added to the static cache contract.

## Production closure gate

Device/Raspberry smoke must verify:

- manifest is served successfully;
- service worker registers;
- mobile menu exposes installation on a supporting browser;
- installation succeeds;
- launching the installed app opens the mobile presentation;
- internal navigation stays functional;
- switching to Desktop remains possible;
- offline navigation reaches the fallback rather than stale PHP content;
- reconnecting restores current dynamic data.

No database or PHP domain change is part of Phase 9.4.
