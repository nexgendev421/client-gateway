# Security and production deployment

SiyazPOS Router is a control plane. It stores customer/store configuration, device registrations, licence state and audit records. Customer POS systems remain on separate customer VPSs; devices use those POS endpoints directly after provisioning. Router must not proxy sales or store customer transaction data.

## Production assumptions

- Serve the Router only over HTTPS with a current TLS certificate. Redirect HTTP at the web server or load balancer, and configure Laravel trusted proxies only for the actual proxy addresses. Do not trust arbitrary forwarded headers from the public internet.
- Set `APP_ENV=production`, `APP_DEBUG=false`, a private `APP_KEY`, production database credentials, and an appropriate log level. The checked-in `.env.example` is for local development and contains placeholders only.
- Set `APP_URL` and `ROUTER_PROVISIONING_ISSUER` to the public HTTPS Router origin. Set `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`, and `SESSION_HTTP_ONLY=true`. Keep `SESSION_SAME_SITE=lax` unless a reviewed integration requires a different policy.
- Use a production database and a shared, durable cache store for rate limits and distributed locks when running multiple app instances. Restrict database, cache, and Redis network access to application hosts.
- Run `php artisan config:cache`, `php artisan route:cache`, and `php artisan view:cache` as part of deployment. Never expose `.env`, `storage`, logs, backups, or source-control metadata through the web server.
- Ensure error reporting goes to a protected operational channel; public pages and APIs must not reveal exception messages, SQL, stack traces, or infrastructure details.

## Required secrets and key handling

- Generate a unique `APP_KEY` per environment. Back it up in an encrypted secret manager: rotating it invalidates encrypted database values, including MFA secrets, unless those values are deliberately re-encrypted.
- Generate an RSA signing key of at least 2048 bits for provisioning and licence tokens. Keep the private key outside the repository and web root, readable only by the application service account, and provide its path/passphrase through a secret manager or protected environment configuration. Never put a private key in a database, container image, or source control.
- Configure `ROUTER_PROVISIONING_KEY_ID`, `ROUTER_PROVISIONING_PRIVATE_KEY_PATH`, and `ROUTER_PROVISIONING_PUBLIC_KEYS`. The public key map must use key IDs and point to public PEM files. Do not remove an old public key until all short-lived tokens signed with it have expired and all clients have received the replacement trust configuration.
- For key rotation, add the new public key to verifiers first, switch signing to a new unique key ID/private key, verify issuance and exchange, then retire the old public key after the overlap period. Keep private-key backups encrypted and access audited.
- Keep database, mail, cloud, cache, and monitoring credentials in the deployment secret manager. The Router must never log activation codes, device credentials, raw passwords, TOTP secrets, recovery codes, private keys, or complete signed tokens.

## Security controls in the application

- Admin authentication uses sessions, CSRF protection, login throttling, active-account checks, role authorization, and MFA controls. Super administrators require MFA by default (and in production); local development may explicitly set `ROUTER_REQUIRE_SUPER_ADMIN_MFA=false`. Long-term remember-me is not enabled by default.
- Device activation codes are stored as hashes, rate limited, expiry and use-count checked transactionally, and only shown once. Device credentials are high entropy, stored as hashes, and revocable/rotatable.
- Provisioning exchange verifies an RS256 signature, issuer, audience, purpose, timing claims, and one-time token ID. Licence documents are signed and contain no customer POS transactional data.
- POS health checks accept public HTTPS endpoint hosts only in production, resolve and validate public addresses, pin those addresses for the request, refuse redirects, verify TLS, apply short timeouts, and cap response bodies.
- Global response headers prevent MIME sniffing and framing; HSTS is sent only for secure requests in production. Inline scripts currently used by the activation handoff mean a strict Content Security Policy needs a nonce/hash migration before it can safely be enabled.

## Release checklist

- [ ] HTTPS is enabled end to end; proxy trust is limited to known proxy IPs.
- [ ] `APP_DEBUG=false`; secrets are supplied outside Git; `.env` and signing keys are not web-accessible.
- [ ] `APP_KEY` and RSA signing keys are backed up and access restricted.
- [ ] Signing key ID and public-key overlap/rotation procedure have been exercised in staging.
- [ ] Secure, HTTP-only session cookies and session encryption are enabled.
- [ ] Rate-limit cache is shared and durable across application instances.
- [ ] Database/cache/firewall access is restricted; encrypted backups and restore procedures are verified.
- [ ] Scheduled tasks and queue workers run under restricted service accounts; logs and audit access are restricted.
- [ ] Production migrations, full test suite, and asset build pass before release.
- [ ] Operational monitoring alerts on failed logins, repeated activation failures, health-check failures, storage capacity, and backup failures without collecting secrets.
