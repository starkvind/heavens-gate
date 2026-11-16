# Content API v1 — staging branch

Read-only, token-protected access to the published website data, reusing the public search catalog, search query builder and forum topic publication gate.

## Deployment prerequisites
- Set `HG_CONTENT_API_TOKEN` to a long random secret in the Raspberry's existing `config.env` or PHP process environment (never commit it). A separate secret is intentional; do not reuse `HG_TOOL_API_TOKEN`.
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
- Primary sections are projected using the existing public search catalog fields (ID, title, description/excerpt and secondary label); the endpoint is not a full mechanical character sheet export.
- Other rule sections use allowlisted tables and allowlisted fields; schema or publication discrepancies must be checked on the Raspberry before deployment.
- Forum data is restricted to `fact_tools_topic_viewer.is_active=1`; raw SMF BBCode is returned.
- Maximum 25 rows/request, no arbitrary SQL, no writes, no shared dice tool token.
- The static OpenAPI file `admin_docs/CONTENT_API_OPENAPI.yaml` is intended for import into an API-action style connector; it does not automatically install a ChatGPT plugin or MCP server.
- This branch has not been smoke-tested against the live Raspberry database. Do not deploy without validating parity of public/hidden records, token handling and representative requests.
