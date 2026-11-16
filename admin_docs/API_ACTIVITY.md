# API activity administration

Open `/talim?s=admin_api_activity` after signing in, or choose **Sistema → Actividad de la API**. The same responsive authenticated Admin controller serves desktop and mobile; the existing `/talim` exclusion from the legacy mobile renderer remains intact. The page filters by UTC day, authenticated client class and outcome, displays Madrid timestamps and paginates 50 requests at a time. A link opened before login returns to this registered local section after authentication.

Only `/api/v1/content` and `/mcp` are audited, including rejected methods, invalid credentials, PHP failures and MCP application errors returned with HTTP 200. Counts, elapsed application time and encoded JSON response bytes describe requests reaching these controllers; Apache rejections, OAuth consent/token endpoints and legacy tool APIs are outside this journal. HTTP compression and transport/header bytes are not measured.

Identity is based on successful existing authentication: `chatgpt-oauth` represents the fixed ChatGPT OAuth client and existing administrator subject; `rest-shared` represents the existing shared API credential. This does not identify different humans or programs sharing that credential. `unauthenticated` means authentication failed; `unchecked` means a method was rejected before authentication. Program category is inferred from the declared User-Agent and is not an authenticated identity. Origin is an HMAC pseudonym of Apache's REMOTE_ADDR, never a forwarded header; NAT/proxies can group multiple clients under one origin.

No token, cookie, raw IP, full User-Agent, query text, identifier/filter value, request body or response content is stored. Only a fixed resource vocabulary, recognized MCP operations/tool and the names of present filters are accepted. Rows contain UTC timestamp, endpoint, HTTP method/status, client, program category, origin pseudonym, operation/resource/filter names, outcome, duration and response byte count. The journal starts at installation; it does not reconstruct history.

## Private installation

Provision `/var/lib/heavensgate/api-audit` outside all web roots, owned by the PHP worker (`www-data` on Raspberry), mode 0700. Generate a new independent 32-byte `origin.key` there, owned by the same user, mode 0600. Do not display or commit it. The default location is fixed in the domain helper; `HG_API_AUDIT_DIR` is an optional process-environment override for isolated deployments/tests and must never be writable from request input.

Records use daily UTC JSONL files, mode 0600, exclusive write locks, a 4 MiB per-day cap and a 30-day retention window (today plus 29 previous UTC days, at most 120 MiB). Each write removes expired day files. Install a daily timer running `php tools/api_audit_retention.php` as the PHP worker so expiry also happens without traffic. At the cap subsequent records are omitted; the page warns that the day may be incomplete. Missing/unwritable storage produces a generic server-log warning without changing API authentication or corrupting JSON responses. Retain the private directory permissions; do not create an Alias or public download endpoint.

The view is read-only and uses the existing administrator session guard and cache/security headers. It adds no login mechanism, API credentials, database tables, destructive controls or public reporting endpoint. Web-process-owned logs are operational telemetry, not a tamper-proof forensic record.

## Checks

`php .github/ci/php-api-audit-regression.php` covers privacy normalization, runtime recording, concurrent appends, size limits, pagination/filter aggregates, retention and denied admin access. Run existing auth/MCP/admin architecture checks too. Production acceptance additionally requires actual REST/MCP requests producing private rows, an authenticated admin render on desktop/mobile, rejected unauthenticated access and unchanged OAuth discovery/API/admin smoke results. Never put test tokens in command arguments or capture response tokens in evidence.

Operational diagnosis, deployment evidence and rollback belong in `starkvind/heavens-gate-continuity/system/website/`.
