# PHP Phase 9.2 — Mobile shared-data boundary

## Status

**IMPLEMENTED — CI validation required at final branch HEAD — Raspberry smoke pending.**

Website branch: `php-refactor`.

Phase 9.2 base:

`c9f689d44e68cebf6ca2279ec5f1f2757bee15ee`

## Architectural decision

The mobile renderer is retained as an explicit alternate presentation.

Desktop and mobile must share application/data behavior. They may differ in:

- HTML structure;
- CSS;
- JavaScript interaction;
- touch/mobile affordances;
- presentation-specific pagination/layout choices.

They must not independently own:

- SQL/data access;
- business rules;
- entity visibility;
- chronicle scope semantics;
- canonical route identity;
- shared menu/catalog rules.

The intended shape is:

```text
request
  -> router
  -> domain queries / shared rules
  -> desktop presentation
  -> mobile presentation
```

## Shared ownership introduced

### Navigation

Shared owner:

`app/domains/navigation/queries.php`

Both:

- `app/partials/main_menu.php`;
- `app/mobile/mobile_menu.php`;

use the same parent/child menu rows and dynamic season catalog preparation.

Mobile remains free to hide Admin or deliberately desktop-only visualizations as a presentation capability decision.

### Chronicle scope

Shared owner:

`app/domains/chronicles/scope.php`

The former mobile-only helper:

`app/mobile/helpers/chronicle_scope.php`

was removed.

Mobile consumers now use the shared chronicle exclusion configuration rather than maintaining a parallel rule.

### Gallery catalog/filesystem behavior

Shared owner:

`app/domains/gallery/catalog.php`

Desktop and mobile now share:

- allowed extensions;
- path validation;
- safe directory resolution;
- folder/image discovery;
- title derivation;
- thumbnail/full-image URL resolution;
- thumbnail fallback;
- folder-cover selection.

Desktop and mobile lightbox/HTML/JS remain separate presentation.

## Mobile SQL boundary

Phase 9.2 defines `app/mobile/` as presentation territory.

Direct DB execution is forbidden there. Data queries belong to the owning domain.

## Route boundary

A mobile route is an alternate presentation of a canonical public route. Phase 9.2 does not permit mobile-only application routes.

The existing explicit selector remains supported:

- `?view=mobile`;
- `hg_view=mobile` cookie.

Automatic User-Agent splitting remains retired.

## CI guard

Added:

`.github/ci/php-phase9-mobile-shared-data-audit.py`

Integrated into:

`.github/workflows/php-refactor-checks.yml`

The guard checks:

- no direct DB execution under `app/mobile/**/*.php`;
- no return of the retired mobile-only chronicle-scope helper;
- desktop/mobile navigation use the shared navigation domain;
- desktop/mobile Gallery use the shared Gallery catalog;
- mobile route keys are canonical route keys.

## Deliberately deferred

Phase 9.2 does not:

- merge desktop/mobile markup;
- remove `app/mobile/`;
- remove `?view=mobile`;
- redesign the mobile UI;
- extract all inline mobile JavaScript;
- implement the PWA manifest/service worker/install flow.

Those are later Phase 9 presentation/PWA work.
