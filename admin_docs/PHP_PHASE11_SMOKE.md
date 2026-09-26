# PHP Phase 11.2 — Raspberry final smoke

## Status

**PREPARED — execution on the Raspberry is the production gate.**

Branch: `php-refactor`.

The automated harness is:

`tools/phase11_smoke.sh`

It performs read-only HTTP checks. It does not write database or canon data.

## Raspberry preparation

From the production checkout:

```bash
git switch php-refactor
git pull --ff-only
git status --short
git rev-parse HEAD
bash tools/phase11_smoke.sh | tee /tmp/hg-phase11-smoke.log
```

Requirements:

- working tree clean before the pull;
- checkout remains on `php-refactor`;
- no deploy from `master` yet;
- no database migration is part of Phase 11.

To target another host explicitly:

```bash
HG_BASE_URL=https://naufragio-heavensgate.duckdns.org bash tools/phase11_smoke.sh
```

## Automated coverage

The harness checks:

- public hub routes return HTTP 200;
- explicit `?view=mobile` compatibility on representative domains;
- Admin shell remains reachable without performing mutations;
- PWA manifest, worker, offline page and install controller are served;
- forum message embed and the shared avatar-resize controller are served;
- representative legacy aliases canonicalize;
- historical path redirects remain active;
- internal trees remain blocked;
- dynamic HTML preserves mandatory cache revalidation.

A non-zero exit code means the production smoke is not green.

## Manual smoke

The automated HTTP harness is necessary but not sufficient. Complete these read-only/manual checks after it passes.

### Classic desktop

Open:

- https://naufragio-heavensgate.duckdns.org/home
- https://naufragio-heavensgate.duckdns.org/characters
- https://naufragio-heavensgate.duckdns.org/seasons
- https://naufragio-heavensgate.duckdns.org/rules
- https://naufragio-heavensgate.duckdns.org/powers
- https://naufragio-heavensgate.duckdns.org/gallery
- https://naufragio-heavensgate.duckdns.org/tools/forum-topic-viewer

Confirm:

- Classic remains the canonical desktop appearance;
- no section receives an unexpected theme/background color;
- main menu, tabs and dense tables are usable;
- no obvious layout regression appears at normal desktop width;
- Home, Rules and Gallery do not show the slowdown that motivated the performance pass.

### Forum `hg_avatar`

Open at least one real forum topic containing several `hg_avatar` embeds, including one long post.

Confirm:

- every iframe receives its own height;
- long dialogue is not clipped;
- there is no fixed-height truncation;
- loading one embed does not add repeated margin/height to previous embeds;
- reflow after fonts/images load does not start a feedback loop;
- short embeds do not leave large empty blocks.

This is the manual regression for the five September 7 hotfixes reconciled in Phase 11.0.

### Mobile compatibility

Open:

- https://naufragio-heavensgate.duckdns.org/home?view=mobile
- https://naufragio-heavensgate.duckdns.org/characters?view=mobile
- https://naufragio-heavensgate.duckdns.org/gallery?view=mobile
- https://naufragio-heavensgate.duckdns.org/rules?view=mobile
- https://naufragio-heavensgate.duckdns.org/timeline?view=mobile

Confirm:

- pages contain the same underlying data as desktop;
- internal navigation remains functional;
- switching to Desktop remains possible;
- returning to mobile still works;
- no User-Agent-only routing has reappeared.

Do not remove `?view=mobile` in Phase 11.

### Gallery

Open:

- https://naufragio-heavensgate.duckdns.org/gallery

Confirm:

- initial page uses thumbnails;
- opening the lightbox loads the full image;
- previous/next navigation works;
- closing and reopening the lightbox works;
- no eager download storm of every full-size image is visible.

### PWA/device

Open the mobile presentation in a supporting device/browser.

Confirm:

- install UI is offered when supported;
- installation succeeds;
- launching the installed app enters `/home?view=mobile`;
- internal navigation works in standalone mode;
- switching to Desktop remains possible;
- offline navigation reaches `/offline.html`, not stale dynamic PHP;
- reconnecting restores live data.

### Admin read-only smoke

Open:

- https://naufragio-heavensgate.duckdns.org/talim

Authenticate normally and browse representative list/detail screens without saving changes.

Confirm:

- login/session works;
- Admin shell renders;
- character, chapter/document and configuration/navigation screens load;
- CSRF/auth boundaries do not block normal read-only navigation;
- do not create, edit or delete content as part of this smoke.

## Pass criteria

Phase 11.2 passes only when:

1. `tools/phase11_smoke.sh` exits 0;
2. Classic desktop looks correct;
3. the real-forum `hg_avatar` regression passes;
4. mobile compatibility passes;
5. Gallery passes;
6. PWA/device behavior passes where a supporting device is available;
7. Admin read-only navigation passes.

Record any failure before changing code. Do not compensate for a failing smoke by weakening CI or broadening Phase 11 scope.
