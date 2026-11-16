# Private MCP OAuth for ChatGPT Web

**Deployment state:** source code only until PR merge and Raspberry `git pull`. OAuth remains **disabled by default**. No impact on `/talim` authentication, sessions, password storage or routes.

## Why another credential?

ChatGPT Web's custom MCP connection does not accept a manually entered Bearer API key. It discovers OAuth 2.1 metadata, uses the authorization-code flow with PKCE S256 and sends an OAuth access token as a Bearer header. This module protects **only** `/mcp`; the separate REST `/api/v1/content` continues to use `HG_CONTENT_API_TOKEN`.

## Activation — only when SSH access is available

1. After the code is merged, run the usual `git pull` in the website checkout. Without the next step OAuth reports 503 and stays closed.
2. On the Raspberry, generate a **new independent signing secret** with `openssl rand -hex 32`. Do not use the existing API token or the database password.
3. Open the existing **private** `config.env` actually used by Heaven's Gate (or configure the PHP-FPM process environment) and append:

   ```ini
   HG_MCP_OAUTH_SECRET="<64 hex characters generated locally>"
   ```

   Do not add this entry to a tracked file or GitHub. Ensure that the existing private config is readable by PHP and protected against HTTP access. The PHP helper `hg_tool_api_config` reads the process environment first and then the existing `config.env` locations. No schema migration is needed.
4. If PHP-FPM reads the value from `config.env`, no service installation or process restart should be required. If using a PHP-FPM environment variable, reload the service as appropriate.
5. Test the metadata in a browser (without revealing the secret):
   - `https://naufragio-heavensgate.duckdns.org/.well-known/oauth-protected-resource`
   - `https://naufragio-heavensgate.duckdns.org/.well-known/oauth-authorization-server`
6. In ChatGPT Web create a **new custom MCP connection** to `https://naufragio-heavensgate.duckdns.org/mcp`, selecting **OAuth**, not `Sin autenticación`.
7. During linking, the authorization page on Heaven's Gate requires an **existing authenticated administrator session**. If not logged in, open `/talim` in the same browser and log in normally, then return to the OAuth authorization tab, refresh it and approve **content:read**. ChatGPT receives a 1-hour OAuth token, never the admin password.
8. Check that tools/list and a published character search work. Confirm that a token issued for another audience, an expired token or a request without auth is rejected.

## Technical model and limitations

- OAuth endpoints are `/oauth/authorize`, `/oauth/token`, `/.well-known/oauth-protected-resource` and `/.well-known/oauth-authorization-server`.
- The client is locked to ChatGPT's issuer-aware CIMD identifier `https://chatgpt.com/oauth/client.json` and redirect `https://chatgpt.com/connector_platform_oauth_redirect`. If the live ChatGPT client uses another callback mode, update the exact allowlist after inspecting it; never accept arbitrary redirect URIs.
- PKCE S256 is mandatory. The consent form uses a CSRF token and only an authenticated, time-bounded `/talim` session can approve. The admin login itself is unchanged.
- OAuth authorization codes are single-use, expire after 5 minutes and are stored in a PHP-owned 0700 directory under `sys_get_temp_dir()` with individual 0600 files. If the PHP process cannot write securely there, issuing a code fails closed and the server needs an operational fix. This is intentionally not a MariaDB migration.
- HMAC-SHA256 access tokens have one-hour expiry and explicit issuer, audience and `content:read` scope. Token rotation/revocation is achieved by changing `HG_MCP_OAUTH_SECRET`. **Refresh tokens are not issued**: after expiry ChatGPT may require account reconnection; this is a deliberate minimal-scope first implementation, not a complete multiuser identity service.
- When the OAuth secret is configured, `/mcp` stops accepting the old `HG_CONTENT_API_TOKEN` or the staging fallback, and accepts OAuth access tokens exclusively.
- Existing REST API behavior, including the temporary credential due to expire 2026-11-18 00:00 CET, is separate. Remove the temporary fallback before treating the REST API as production-ready.
- This feature has no guaranteed live readiness until Raspberry smoke tests, existing PHP session persistence, writable temp storage, and exact ChatGPT OAuth callback behavior are confirmed.
- The API's unpublished/visibility gates still require independent production testing; OAuth does not repair data visibility inconsistencies.

## Quick diagnostics

| Observation | Meaning |
| --- | --- |
| Metadata 503 | No valid `HG_MCP_OAUTH_SECRET` configured |
| Consent page says not logged in | Sign in normally at `/talim`, return and refresh |
| OAuth code storage unavailable | PHP temp directory not writable as a 0700 private directory |
| OAuth callback rejected | Check exact CIMD client ID/redirect URL against current ChatGPT settings |
| MCP 401 despite successful consent | Check token issuer, scope, expiry, secret consistency and Authorization forwarding |
| Reauthentication after 1 hour | By design: no refresh tokens in this first iteration |
