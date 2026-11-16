# Content API v1 — staging branch

Read-only, token-protected access to the published website data, reusing the public search catalog, search query builder and forum topic publication gate.

## Deployment prerequisites
- **Temporary testing (until 2026-11-18 00:00 CET)**: a random high-entropy token is accepted using a SHA-256 fingerprint committed under `app/helpers/content_api_auth.php`, allowing a git-pull-only smoke test. The plaintext token is never committed. After that time this access expires automatically.
- **Production**: set `HG_CONTENT_API_TOKEN` to a new long random secret in the Raspberry's existing `config.env` or PHP process environment (never commit it). When configured, it overrides and disables the temporary test token. Do not reuse `HG_TOOL_API_TOKEN`.
- Apache/PHP-FPM must forward the `Authorization` header as `HTTP_AUTHORIZATION`.
- Keep HTTPS enabled. No port opening or daemon needed.
- No MariaDB migrations. No write route.

## Example
```sh
curl -i https://naufragio-heavensgate.duckdns.org/api/v1/content
# 401 if configured; 503 if missing config
curl -H "Authorization: Bearer <REDACTED>" 'https://naufragio-heavensgate.duckdns.org/api/v1/content?resource=catalog'
curl -H "Authorization: Bearer <REDACTED>" 'https://naufragio-heavensgate.duckdns.org/api/v1/content?resource=characters&q=Brisa&limit=5'
curl -H "Authorization: Bearer <REDACTED>" 'https://naufragio-heavensgate.duckdns.org/api/v1/content?resource=forum&topic_id=123&page=1'
```

## Limits and honest scope
- Paginated lists reuse the existing public search catalog fields. ID detail fetches reuse domain queries for characters (including linked chapters and traits), episodes (participants/events), seasons (episodes), systems (forms/resources), rule entries and powers. The complete mechanical character-sheet export is not implemented.
- Other rule sections use explicit tables and domain queries, no dynamic schema introspection. Public visibility and any schema discrepancies must still be checked on the Raspberry before deployment.
- Forum data is restricted to `fact_tools_topic_viewer.is_active=1`; raw SMF BBCode is returned.
- Maximum 25 rows/request, no arbitrary SQL, no writes, no shared dice tool token.
- The route forwards the Authorization header through Apache's rewrite environment for PHP-FPM; validate with the deployed Apache modules.
- The static OpenAPI file `admin_docs/CONTENT_API_OPENAPI.yaml` is intended for import into an API-action style connector; it does not automatically install a ChatGPT plugin or MCP server.
- The temporary test token requires no secret editing on the Raspberry. It expires at 2026-11-18 00:00 CET and should be removed from code promptly, even if expired. After expiry, the endpoint returns 401 without a configured permanent secret. Production still requires secure configuration of HG_CONTENT_API_TOKEN.
- This branch has not been smoke-tested against the live Raspberry database. Do not deploy without validating parity of public/hidden records, token handling and representative requests.

## Staging smoke (after deploying on Raspberry)

1. Without Authorization, GET `/api/v1/content?resource=catalog` must return 401 JSON.
2. With the temporary token in the `Authorization: Bearer ...` header, the same request must return 200 JSON with a catalog.
3. Try small, published samples: `characters`, `episodes`, `seasons`, `inventory`, `powers`, `systems`, `maneuvers`, `rites`, `forum`, and an active `topic_id`.
4. Test invalid/unknown resource 404, non-GET 405, invalid token 401, and paging with `limit=2`.
5. Verify no unpublished material appears and errors do not reveal SQL/internal file paths.
6. Remove the staging fingerprint in a follow-up change and rotate to a separately configured production token. Do **not** use the temporary token as a permanent connector credential.

Unknown URL paths and a hidden endpoint name are not security controls. Authentication must remain mandatory.
