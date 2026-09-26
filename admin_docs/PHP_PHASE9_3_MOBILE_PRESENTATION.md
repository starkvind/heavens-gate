# PHP Phase 9.3 — Mobile presentation cleanup

## Status

**IMPLEMENTED — CI validation pending at final branch HEAD — Raspberry smoke pending.**

Branch: `php-refactor`.

## Scope

Phase 9.3 keeps the dedicated mobile renderer and cleans presentation ownership without changing data/application behavior.

The rule remains:

```text
canonical route/data/application behavior
  -> desktop presentation
  -> mobile presentation
```

This phase changes presentation structure only.

## Changes

### Executable JavaScript moved out of mobile PHP

Interactive behavior now lives in:

`assets/js/hg-mobile.js`

The following mobile interactions were externalized:

- Gallery lightbox/navigation/BBCode copy;
- soundtrack YouTube player;
- recent-search history;
- inventory copy action;
- organization Markdown copy;
- character action filtering;
- character form visual modifiers;
- form-dependent maneuver rendering.

Dynamic PHP state crosses into JavaScript through inert HTML data attributes/hidden data containers, not executable inline PHP/JS blocks.

### Thin controller/view boundaries

These routes now separate data/controller preparation from HTML presentation:

- `app/mobile/controllers/gallery.php` -> `app/mobile/views/gallery.php`;
- `app/mobile/controllers/search.php` -> `app/mobile/views/search.php`;
- `app/mobile/controllers/soundtrack.php` -> `app/mobile/views/soundtrack.php`.

The existing Character detail controller/view split remains in place.

This is deliberately selective: controllers are split where doing so materially reduces mixed presentation code. Phase 9.3 does not rewrite every mobile controller merely to reduce line counts.

## Permanent guard

Added:

`.github/ci/php-phase9-mobile-presentation-audit.py`

Integrated into PHP Refactor Characterization.

It enforces:

- no executable inline `<script>` blocks under `app/mobile/`;
- no inline event-handler attributes under `app/mobile/`;
- no inline `<style>` blocks under `app/mobile/`;
- Gallery/Search/Soundtrack controllers continue delegating to their views;
- mobile interaction behavior remains present in the owned JS asset;
- Search and Organization dynamic-data bridges remain inert HTML data.

## Deliberately unchanged

Phase 9.3 does not:

- alter queries or shared domain logic;
- redesign mobile HTML/CSS;
- merge desktop/mobile markup;
- change route semantics;
- implement PWA/installability;
- add offline caching.

PWA work remains the next Phase 9 step after production smoke closes 9.3.
