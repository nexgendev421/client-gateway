# Production configuration

Use this guide when creating a production `.env`. Keep the file outside source control, restrict it to the application service account, and provide secrets through a protected secret manager where available. `.env.example` intentionally contains development placeholders and is not a production template.

## Application and HTTPS

Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://router.example.com`, and a generated environment-specific `APP_KEY`. Set `ROUTER_PROVISIONING_ISSUER` to the same canonical HTTPS origin. Never use a wildcard or customer POS endpoint as the issuer. Terminate TLS with a trusted certificate and redirect HTTP to HTTPS at the edge.

If a reverse proxy or Cloudflare sits in front of Laravel, configure trusted proxies to the actual proxy address ranges in the web server/Laravel deployment. The current application does not trust forwarded proxy headers globally by default. Do not enable trust-all proxies on an origin reachable directly from the internet. Ensure the origin firewall accepts web traffic only from the intended proxy when that topology is used. Validate that Laravel sees HTTPS so secure cookies and HSTS work.

## Sessions, cache and rate limits

Production defaults for Laravel session encryption and secure cookies are enabled when `APP_ENV=production`; set `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, and `SESSION_SAME_SITE=lax` explicitly to make deployment intent visible. Use `SESSION_DRIVER=database` or a protected Redis service. Do not set a broad cookie domain unless subdomain sharing is deliberately required.

The local template uses file cache and sync queues. For multiple web instances, configure a shared Redis or database cache for rate limits and atomic locks (`CACHE_STORE=redis` with private Redis networking is a typical choice). Configure a durable queue only when queued work is introduced; the current scheduled endpoint checks run through Laravel Scheduler and do not require a queue worker. A single-node deployment can use database cache/queue if their migrations and workers are maintained. Do not use the array cache in production.

## Database, logs and filesystem permissions

Use a dedicated least-privilege MySQL/MariaDB user, TLS database transport where available, and network rules that allow only application hosts. Store credentials as secrets. Back up the database and test restoration. Configure `LOG_CHANNEL=daily`, a suitable `LOG_LEVEL=warning` or `error`, and retention/rotation through Laravel and the host. Ensure logs are outside the web root, readable only by operators, and never include tokens, activation codes, passwords, MFA secrets, or signing keys.

The web process needs write access only to `storage` and `bootstrap/cache` (plus explicitly configured temporary locations). Keep source, `.env`, `vendor`, private key files, database dumps and backups outside public document roots. Use `APP_DEBUG=false`; generic application error handling is not a replacement for private server-side error monitoring.

## Signing keys

Generate a unique RSA key pair (minimum 2048-bit) for each environment. Keep the private key in a secret mount or protected path outside the repository and document root. Set `ROUTER_PROVISIONING_PRIVATE_KEY_PATH`, `ROUTER_PROVISIONING_PRIVATE_KEY_PASSPHRASE` if encrypted, and a unique `ROUTER_PROVISIONING_KEY_ID`. Map that key ID to its public PEM path in `ROUTER_PROVISIONING_PUBLIC_KEYS` as a JSON object, for example `{"router-provisioning-v2":"/etc/siyazpos-router/keys/provisioning-v2.pub.pem"}`. The example path and key ID are not secrets; never put private PEM contents into an environment variable committed to source control.

For rotation, install new public key(s) on all verifiers first, switch issuer to a new private key and key ID, verify signing and exchange, then retain old public keys until all issued short-lived provisioning tokens have expired and clients have the replacement trust material. Keep encrypted backups, restrict key read access, and record the rotation date and operators. Licence verification must retain any previous public key for the lifetime of offline licences signed by it.

## Scheduler, queues and deployment caches

Run the Laravel scheduler once per minute under the application user (`* * * * * cd /path/to/router && php artisan schedule:run >> /dev/null 2>&1`). `schedule:list` should show the POS health check and retention pruning jobs. Add a process supervisor only for queue workers if the queue connection is changed from `sync` and actual queued jobs are introduced.

After updating environment/configuration, run `php artisan config:cache`, `php artisan route:cache`, and `php artisan view:cache`. Rebuild Vite assets in the release pipeline. Clear/rebuild these caches when configuration changes; Laravel processes do not read `.env` directly once config is cached.

## Example production settings (values are deployment-specific)

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://router.example.com
APP_KEY=<unique-generated-secret>
ROUTER_PROVISIONING_ISSUER=https://router.example.com
ROUTER_PROVISIONING_KEY_ID=router-provisioning-v2
ROUTER_PROVISIONING_PRIVATE_KEY_PATH=/etc/siyazpos-router/keys/provisioning-v2.key.pem
ROUTER_PROVISIONING_PUBLIC_KEYS={"router-provisioning-v2":"/etc/siyazpos-router/keys/provisioning-v2.pub.pem"}
SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
CACHE_STORE=redis
QUEUE_CONNECTION=sync
LOG_CHANNEL=daily
LOG_LEVEL=warning
```

Replace all placeholders and provide database/Redis/private-key passphrases through the protected deployment secret store. Never commit this example after filling it with real values.
