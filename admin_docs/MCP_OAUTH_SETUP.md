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
7. During linking, the authorization page on Heaven's Gate requires an **existing authenticated administrator session**. If not logged in, open `/talim` in the same browser and log in normally, then return to the OAuth authorization tab, refresh it and approve **content:read**. ChatGPT receives a 1-hour OAuth access token and a **rotating refresh token** (90-day absolute family lifetime), never the admin password. When ChatGPT supports refresh, it should renew access without asking you to approve every hour.
8. Check that tools/list and a published character search work. Confirm that a token issued for another audience, an expired token or a request without auth is rejected.

## Technical model and limitations

- OAuth endpoints are `/oauth/authorize`, `/oauth/token`, `/.well-known/oauth-protected-resource` and `/.well-known/oauth-authorization-server`.
- The client is locked to ChatGPT's issuer-aware CIMD identifier `https://chatgpt.com/oauth/client.json` and redirect `https://chatgpt.com/connector_platform_oauth_redirect`. If the live ChatGPT client uses another callback mode, update the exact allowlist after inspecting it; never accept arbitrary redirect URIs.
- PKCE S256 is mandatory. The consent form uses a CSRF token and only an authenticated, time-bounded `/talim` session can approve. The admin login itself is unchanged.
- OAuth authorization codes are single-use, expire after 5 minutes and are stored in a PHP-owned 0700 directory under `sys_get_temp_dir()` with individual 0600 files. Refresh family records use this same directory and store **only HMAC digests**, never plaintext refresh credentials. Rotations use an exclusive file lock to prevent concurrent reuse; old-token replay revokes the family. If the PHP process cannot write securely there, issuance fails closed. **Important:** system temporary directories can be cleaned on reboot or by OS housekeeping; tokens may require reauthorization earlier than 90 days. Long-term persistence has NOT been proven on the Raspberry.
- HMAC-SHA256 access tokens have one-hour expiry and explicit issuer, audience and `content:read` scope. Token rotation/revocation is achieved by changing `HG_MCP_OAUTH_SECRET`. **Refresh tokens are issued and rotated on every refresh**. The old token is immediately invalidated; reuse revokes the entire family. Refresh families have an absolute **90-day lifetime** measured from initial authorization, so reauthorization will be required afterwards, or sooner if tokens are revoked, storage is cleaned, or ChatGPT does not complete refresh automatically.
- When the OAuth secret is configured, `/mcp` stops accepting the old `HG_CONTENT_API_TOKEN` or the staging fallback, and accepts OAuth access tokens exclusively.
- Existing REST API behavior, including the temporary credential due to expire 2026-11-18 00:00 CET, is separate. Remove the temporary fallback before treating the REST API as production-ready.
- This feature has no guaranteed live readiness until Raspberry smoke tests, existing PHP session persistence, writable temp storage, and exact ChatGPT OAuth callback behavior are confirmed.
- The API's unpublished/visibility gates still require independent production testing; OAuth does not repair data visibility inconsistencies.

## Refresh token rotation

The authorization-code exchange returns `access_token` (60 minutes) and `refresh_token`. ChatGPT may call `POST /oauth/token` with `grant_type=refresh_token`, `client_id` and `refresh_token`. Each successful call returns a new access token **and a different refresh token**. The old refresh token is immediately invalid. Two clients using the same refresh token concurrently are treated as replay, and the active family is revoked. Issuing a new OAuth authorization gives a new refresh family. A new `HG_MCP_OAUTH_SECRET` invalidates all older signed access tokens and refresh families.

For the first live test: authorize once, confirm content tools work, then verify that ChatGPT can access tools after an hour without a second consent. Do not log or screenshot any tokens. The PHP temp directory must survive for background renewal to remain connected; no durability guarantee exists yet.

## Quick diagnostics

| Observation | Meaning |
| --- | --- |
| Metadata 503 | No valid `HG_MCP_OAUTH_SECRET` configured |
| Consent page says not logged in | Sign in normally at `/talim`, return and refresh |
| OAuth code storage unavailable | PHP temp directory not writable as a 0700 private directory |
| OAuth callback rejected | Check exact CIMD client ID/redirect URL against current ChatGPT settings |
| MCP 401 despite successful consent | Check token issuer, scope, expiry, secret consistency and Authorization forwarding |
| Reauthentication after 1 hour | Check ChatGPT refresh-token support and POST `/oauth/token` refresh grant; should normally renew automatically |
| Reauthentication before 90 days | Verify PHP temporary storage survives and check for refresh replay/revocation; temporary storage may be cleaned by OS |
| Reauthentication after 90 days | Expected: absolute refresh-family expiry, requires new approval |
