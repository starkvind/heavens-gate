# PHP Phase 10.3 — Request-state classification

## Status

**CLOSED — CI PASS — Raspberry smoke accepted.**

Branch: `php-refactor`.

## Goal

Classify the remaining direct HTTP request state without mechanically rewriting the mature Admin forms.

The public runtime already has an explicit request context and its controllers are guarded at zero direct `$_GET`, `$_POST`, `$_REQUEST` and `filter_input()` reads. Phase 10.3 therefore focuses on the residual Admin/form boundary and presentation preferences.

## Baseline

Phase 10.0 measured:

- raw `$_GET` reads: **192**;
- raw `$_POST` reads: **708**;
- raw `$_COOKIE` reads/writes: **8**;
- raw `$_REQUEST` reads: **1**.

Of those:

- Admin controllers own **189 GET + 706 POST**;
- `app/helpers/admin_ajax.php` owns **1 GET + 2 POST**;
- `app/tools/inspect_db.php` owns **2 GET**;
- the eight cookie accesses are presentation preferences split between mobile view/theme and desktop theme;
- the single `$_REQUEST` access lived in `admin_maneuvers.php`.

## Decision

Direct GET/POST reads inside the existing Admin controllers are classified as an intentional **admin form/action boundary**.

They are not architectural debt merely because they use PHP form superglobals. Rewriting dozens of stable CRUD forms into another request abstraction would add churn without changing the ownership boundary.

What is not accepted:

- raw request state in public controllers;
- `$_REQUEST`, because its GET/POST precedence is implicit;
- authentication or authorization decisions based on client cookies;
- new request-global owners outside the classified boundaries.

## Cleanup

`admin_maneuvers.php` no longer uses `$_REQUEST['maneuver_id']`.

The maneuver id now has explicit transport precedence using `filter_input(INPUT_POST, ...)` with a GET fallback. Existing GET selection and POST save behavior remain unchanged while the ambiguous superglobal disappears.

Result:

- Admin raw GET reads: **189**;
- Admin raw POST reads: **706**;
- raw `$_REQUEST` reads: **0**.

## Classified owners

### Admin form/action boundary

`app/controllers/admin/*.php`

May consume GET/POST form state directly. CI freezes the current raw ceilings rather than requiring a cosmetic rewrite.

### Admin transport helper

`app/helpers/admin_ajax.php`

May inspect the AJAX GET/POST compatibility flags and the POST CSRF fallback used by existing Admin requests.

### Admin diagnostic boundary

`app/tools/inspect_db.php`

May read its GET mode selector because it is included as an authenticated Admin diagnostic surface.

### Presentation preference cookies

Only these files may directly own the current preference cookies:

- `app/helpers/mobile_detection.php` — desktop/mobile view preference;
- `app/mobile/mobile_index.php` — mobile theme preference;
- `app/presentation/desktop_context.php` — desktop theme preference.

These cookies choose presentation only. Admin authentication continues to use server-side PHP session state.

## Guards

`.github/ci/php-phase10-request-state-audit.py` now rejects:

- any unclassified direct request-state owner;
- any raw `$_REQUEST`;
- cookie ownership outside the three presentation-preference files;
- GET/POST growth above the classified Admin ceilings.

The existing public request-state audit remains stricter: public controllers and public support code stay at zero direct request reads.

The older Admin debt guard is tightened to:

- GET: **189**;
- POST: **706**;
- REQUEST: **0**.

The Phase 7 global regression baseline now also requires zero raw `$_REQUEST`.

## Validation

Implementation checkpoint:

- public direct request reads: **0**;
- Admin raw GET: **189**;
- Admin raw POST: **706**;
- raw `$_REQUEST`: **0**;
- preference-cookie accesses: **8**, all classified;
- unclassified request-state owners: **0**;
- PHP Refactor Characterization: PASS;
- Project CI: PASS.

## Closure

The phase was accepted and the refactor proceeded to the next stage without a reported request-state regression.

The desktop-theme smoke also exposed that desktop theming is residual/incomplete rather than a current UX feature; that follow-up is documented separately as post-refactor design work.

Phase 10.3 is closed.
