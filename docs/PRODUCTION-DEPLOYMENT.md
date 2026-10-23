# SiyazPOS Router VPS deployment (HestiaCP)

This guide deploys the central Router service to `siyazposrouter.top` on an Ubuntu VPS managed by HestiaCP. It is an operator-run procedure, not an installer. Replace every placeholder. Do not paste real secrets into Git, tickets, or shell history. Confirm the production topology with the hosting provider before opening firewall ports.

## 1. Prepare the host and domain

1. Point the domain DNS to the VPS. If Cloudflare proxying is enabled, keep the origin web ports restricted to Cloudflare ranges where practical and keep SSH restricted to operator IPs.
2. In HestiaCP, create the domain under a dedicated unprivileged web user, enable SSL, and set a PHP version supported by the repository (`composer.json` currently requires PHP 8.2 or later). Enable required PHP extensions including PDO MySQL, OpenSSL, cURL, Mbstring, XML, Fileinfo, Tokenizer, Ctype, JSON, and Sodium.
3. Create a dedicated MariaDB database and user in HestiaCP. Grant that user access only to the Router database. Keep the database local/private; do not expose port 3306 publicly.
4. Create a dedicated SSH deploy key for this VPS and add only its read access to the Router GitHub repository. Verify host key fingerprints. Do not use a personal SSH key or a broad GitHub token on the server.

## 2. Clone into a private release directory

Connect as the Hestia domain owner, not root. Use a private directory outside the public web root, for example:

```sh
mkdir -p ~/apps
cd ~/apps
git clone --branch main --single-branch git@github.com:YOUR_ORG/YOUR_ROUTER_REPOSITORY.git router
cd router
```

Use the actual repository URL and approved release branch. Never clone into `public_html`. Keep `.git`, source, `vendor`, `.env`, storage, backups, and signing keys outside the web document root.

## 3. Point the website only at Laravel `public/`

Laravel's document root must be the repository's `public` directory. Configure the Hestia Nginx/Apache web template so requests for the domain are served from `/home/WEB_USER/apps/router/public` (using the actual Hestia user and path). Hestia layouts can differ: verify the generated virtual host configuration and ensure its public root resolves to that directory. Do not copy the project root into `public_html` or point the domain at the repository root. Do not make a symlink that allows browsing source or `.env`.

After applying the web template, verify that `/` serves the Laravel app and direct requests for `/.env`, `/vendor/`, `/.git/`, `/storage/logs/`, and `/bootstrap/` do not reveal files. Hestia's PHP-FPM user needs write access to `storage/` and `bootstrap/cache/` only; use the actual service/group model on the host rather than `chmod -R 777`.

## 4. Install dependencies and configure secrets

Install PHP dependencies without development packages:

```sh
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
```

Create `.env` in the private repository root with owner-only permissions, using [PRODUCTION-CONFIGURATION.md](PRODUCTION-CONFIGURATION.md) as the source of required settings. Configure:

- `APP_ENV=production`, `APP_DEBUG=false`, canonical HTTPS `APP_URL`, unique `APP_KEY`.
- Dedicated database host/name/user/password, TLS if supported, and no public database listener.
- HTTPS `ROUTER_PROVISIONING_ISSUER`, unique signing key ID, and key paths.
- Session encryption, secure HTTP-only cookies, durable session/cache storage, and safe log channel/level.
- Mail transport if account recovery/operational notifications are configured.

After creating the protected `.env`, generate an `APP_KEY` once with `php artisan key:generate`; confirm the file remains owner-readable only. Do not run this again on an existing installation. Generate an RSA signing key outside the repository and web root, restrict it to the PHP service account (for example directory mode `0700`, file mode `0600`), and place public PEM files where only intended verifiers can read them. Do not put private PEM material in a public directory or source control. Back up the secrets separately in encrypted storage.

Use a temporary protected secret input method rather than command arguments when possible. Shell commands and process listings can expose values. Never commit `.env` or print it into deployment logs.

## 5. Initialise and verify

From the private application directory:

```sh
php artisan config:clear
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan router:admin:create
```

Run the admin creation command interactively and choose a unique strong password. No default administrator is seeded. Sign in over HTTPS, enroll the super administrator in MFA, and store recovery codes securely. Do not disable MFA to simplify setup.

The storage link is only needed for assets intentionally served from Laravel's public storage disk; product/customer POS uploads do not belong on Router. Inspect the target before creating a link and do not expose private storage.

Run `php artisan route:list`, `php artisan schedule:list`, and the test suite in CI before a release. Ensure `schedule:list` includes the endpoint health check and history pruning tasks. Make a real HTTPS request and confirm that secure cookies, production error pages, and HSTS behave as expected.

## 6. TLS, Cloudflare and network controls

- Use Hestia/Let's Encrypt or another managed certificate, renew automatically, and test renewal before launch.
- If using Cloudflare, choose Full (strict) TLS. Keep the origin certificate valid and configure the origin firewall for Cloudflare address ranges if possible. Do not treat Cloudflare as a substitute for application HTTPS or host firewall rules.
- Configure Laravel trusted proxies only for the known reverse proxy/CDN addresses. Do not accept forwarded host/protocol/IP headers from arbitrary clients. Validate the perceived request scheme and client IP at the origin.
- Allow inbound HTTP/HTTPS only as required, SSH only from operator networks, and no public database/cache ports. Restrict outbound traffic where practical while allowing DNS, HTTPS health checks to approved public POS endpoints, package/security updates, and required mail/monitoring.

## 7. Scheduler, queue and monitoring

Add one cron entry for the Hestia web user to run Laravel Scheduler every minute:

```cron
* * * * * cd /home/WEB_USER/apps/router && php artisan schedule:run >> /dev/null 2>&1
```

Check command path, PHP binary and permissions for the selected host. The current scheduled tasks do not require a queue worker; `QUEUE_CONNECTION=sync` is sufficient. If queued jobs are added later, choose a durable queue, configure a supervised worker, and restart it after each release.

Monitor HTTPS availability, Laravel/PHP-FPM and web server errors, disk space, database capacity, cron execution, backup completion, TLS expiry, and repeated authentication/activation failures. Restrict logs and alerts. Do not include activation codes, device tokens, passwords, MFA data or private-key contents in alert payloads.

## 8. Safe application updates

The repository includes `scripts/deploy.sh` for a deliberately narrow fast-forward release flow. Run it from its checked-out repository using the Hestia web user after a backup and a reviewed release has been merged to `origin/main`:

```sh
cd /home/WEB_USER/apps/router
bash scripts/deploy.sh
```

The script requires Bash, Git, PHP/Artisan, Composer, Node.js/npm, a production `.env`, a populated `APP_KEY`, and a clean checkout on the configured branch (`DEPLOY_BRANCH=main` by default). It fetches the configured `origin`, refuses diverged history, fast-forwards only, enables maintenance mode, installs production Composer dependencies, builds assets, runs migrations, rebuilds caches, requests queue worker restart, and exits maintenance mode. It does not create or rewrite `.env`, generate app or signing keys, reset the database, or push code. It may briefly interrupt activation/control-plane requests during the release. Existing POS sales continue directly against their customer servers.

This is not a universal zero-downtime release manager. It assumes deployment dependencies exist on the host and that the release checkout is disposable and clean. It does not roll back database migrations or replace backups and release review. If it stops after entering maintenance mode, its exit handler tries to restore service; verify with `php artisan up` and inspect the reported failure before retrying.

1. Review and test the release in CI/staging; inspect migrations for destructive changes.
2. Back up the database and current release before deployment.
3. Confirm a clean working tree and expected branch/tag. Never discard server changes with `git reset --hard`, force-pull, or clean commands.
4. Fetch and check out the reviewed immutable release. Keep `.env`, storage, and keys outside release-managed files or ensure Git cannot replace them.
5. Install production dependencies, run reviewed migrations with `--force`, rebuild Laravel caches and Vite assets, and restart/reload PHP-FPM or workers if required.
6. Verify health, login/MFA, an endpoint check, API health, logs, and scheduled tasks. Keep the previous release available until the checks pass.

Use maintenance mode only for changes that need it and only after confirming the Router's short control-plane interruption is acceptable. Devices already assigned to a customer POS endpoint continue to connect directly; Router is not in their normal sales path.

For rollback, point the web root back to the previous known-good release and rebuild/clear caches for that release. Restore a database backup only when a migration or data change requires it and after assessing the impact; never assume rolling back PHP code safely reverses a migration. Keep encrypted, tested backups and a written migration-specific recovery plan. Do not run `migrate:rollback` blindly in production.

## First-launch checklist

- [ ] Web document root is the Laravel `public/` directory only.
- [ ] `.env`, source, logs, backups and signing private keys are not web accessible.
- [ ] Production HTTPS, `APP_DEBUG=false`, encrypted/secure sessions and HSTS are verified.
- [ ] Database and deploy SSH access are least privilege and network restricted.
- [ ] Super administrator exists, MFA is enrolled, and recovery material is stored securely.
- [ ] Signing keys are loaded from protected files and public key configuration matches the issuer.
- [ ] Scheduler runs once per minute; monitoring and encrypted backups are active.
- [ ] Rollback path and tested restore procedure are available before accepting production usage.
