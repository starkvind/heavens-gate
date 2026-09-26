#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${HG_BASE_URL:-https://naufragio-heavensgate.duckdns.org}"
CURL=(curl --silent --show-error --connect-timeout 10 --max-time 30)
failures=0
checks=0

ok() { printf 'PASS  %s\n' "$1"; }
bad() { printf 'FAIL  %s\n' "$1" >&2; failures=$((failures + 1)); }

check_status() {
  local path="$1" expected="$2"
  checks=$((checks + 1))
  local code
  code="$("${CURL[@]}" -o /dev/null -w '%{http_code}' "${BASE_URL}${path}")" || code="curl-error"
  if [ "$code" = "$expected" ]; then ok "$path -> $code"; else bad "$path -> $code (expected $expected)"; fi
}

check_status_any() {
  local path="$1"; shift
  checks=$((checks + 1))
  local code
  code="$("${CURL[@]}" -o /dev/null -w '%{http_code}' "${BASE_URL}${path}")" || code="curl-error"
  for expected in "$@"; do
    if [ "$code" = "$expected" ]; then ok "$path -> $code"; return; fi
  done
  bad "$path -> $code (expected one of: $*)"
}

check_contains() {
  local path="$1" needle="$2"
  checks=$((checks + 1))
  local body
  if ! body="$("${CURL[@]}" "${BASE_URL}${path}")"; then
    bad "$path -> curl failed"
    return
  fi
  if grep -Fq -- "$needle" <<<"$body"; then ok "$path contains $needle"; else bad "$path missing $needle"; fi
}

check_header_contains() {
  local path="$1" needle="$2"
  checks=$((checks + 1))
  local headers
  if ! headers="$("${CURL[@]}" -D - -o /dev/null "${BASE_URL}${path}")"; then
    bad "$path -> curl failed"
    return
  fi
  if grep -Fiq -- "$needle" <<<"$headers"; then ok "$path header contains $needle"; else bad "$path header missing $needle"; fi
}

check_redirect() {
  local path="$1" expected_fragment="$2"
  checks=$((checks + 1))
  local headers code location
  if ! headers="$("${CURL[@]}" -D - -o /dev/null "${BASE_URL}${path}")"; then
    bad "$path -> curl failed"
    return
  fi
  code="$(awk 'toupper($1) ~ /^HTTP\// {code=$2} END {print code}' <<<"$headers")"
  location="$(awk 'BEGIN{IGNORECASE=1} /^Location:/ {sub(/^[^:]+:[[:space:]]*/, ""); sub(/\r$/, ""); print; exit}' <<<"$headers")"
  if [[ "$code" =~ ^30[12378]$ ]] && [[ "$location" == *"$expected_fragment"* ]]; then
    ok "$path -> $code $location"
  else
    bad "$path -> $code $location (expected redirect containing $expected_fragment)"
  fi
}

printf 'Heaven\x27s Gate Phase 11 Raspberry smoke\n'
printf 'Base URL: %s\n\n' "$BASE_URL"

printf '%s\n' '--- Public hubs ---'
for path in   /home /news /status /about /bibliography /search   /characters /chronicles /seasons /chapters /organizations   /documents /inventory /systems /rules /powers /timeline   /music /gallery /maps /players   /tools/dice /tools/forum-avatar /tools/forum-topic-viewer
do
  check_status "$path" 200
done

printf '\n%s\n' '--- Mobile compatibility ---'
for path in   '/home?view=mobile'   '/characters?view=mobile'   '/seasons?view=mobile'   '/gallery?view=mobile'   '/rules?view=mobile'   '/powers?view=mobile'   '/timeline?view=mobile'
do
  check_status "$path" 200
done

printf '\n%s\n' '--- Admin shell ---'
check_status_any /talim 200 302 303

printf '\n%s\n' '--- PWA/static contract ---'
check_status /manifest.json 200
check_status /service-worker.js 200
check_status /offline.html 200
check_status /assets/js/hg-pwa.js 200
check_contains /manifest.json '/home?view=mobile'
check_contains /service-worker.js '/offline.html'

printf '\n%s\n' '--- Forum embed contract ---'
check_status '/forum/message?id=-1&msg=Phase11Smoke' 200
check_contains '/forum/message?id=-1&msg=Phase11Smoke' 'Phase11Smoke'
check_status /assets/js/forum-avatar-embed.js 200
check_contains /assets/js/forum-avatar-embed.js '__hgAvatarResizeInstalled'
check_contains /assets/js/forum-avatar-embed.js 'LEGACY_HEIGHT_PADDING'

printf '\n%s\n' '--- Legacy canonicalization ---'
check_redirect '/?p=imgz' '/gallery'
check_redirect '/?p=dones' '/powers/gifts'
check_redirect '/?p=rites' '/powers/rites'
check_redirect '/?p=totems' '/powers/totems'
check_redirect '/?p=listaobj' '/inventory'
check_redirect '/index.php' '/'
check_redirect '/crop.html' '/tools/crop'

printf '\n%s\n' '--- Private-tree guards ---'
check_status '/app/routing/routes.php' 404
check_status_any '/admin_docs/TECHNICAL_DOCUMENTATION.md' 403 404
check_status '/.github/workflows/security-checks.yml' 404
check_status '/tools/scaffold_section.py' 404
check_status '/sql/audit_gaia0_content.sql' 404

printf '\n%s\n' '--- Dynamic response cache contract ---'
check_header_contains /home 'Cache-Control: no-cache, max-age=0, must-revalidate'

printf '\nChecks: %d | Failures: %d\n' "$checks" "$failures"
if (( failures > 0 )); then
  exit 1
fi

printf 'Automated Raspberry smoke PASS. Complete the manual checks in admin_docs/PHP_PHASE11_SMOKE.md.\n'
