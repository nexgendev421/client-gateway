# SiyazPOS Router VPS deployment (HestiaCP)

This guide deploys the central Router service to `siyazposrouter.top` on an Ubuntu VPS managed by HestiaCP. The first-run web installer writes protected environment settings, generates application/provisioning keys, migrates the database and creates the first super administrator. Replace every placeholder. Do not paste real secrets into Git, tickets, or shell history. Confirm the production topology with the hosting provider before opening firewall ports.

## 1. Prepare the host and domain

1. Point the domain DNS to the VPS. If Cloudflare proxying is enabled, keep the origin web ports restricted to Cloudflare ranges where practical and keep SSH restricted to operator IPs.
2. In HestiaCP, create the domain under a dedicated unprivileged web user, enable SSL, and set a PHP version supported by the repository (`composer.json` currently requires PHP 8.2 or later). Enable required PHP extensions including PDO MySQL, OpenSSL, cURL, Mbstring, XML, Fileinfo, Tokenizer, Ctype, JSON, and Sodium.
3. Create an empty dedicated MariaDB database and user in HestiaCP. Grant that user access only to the Router database. Keep the database local/private; do not expose port 3306 publicly.
4. Create a dedicated SSH deploy key for this VPS and add only its read access to the Router GitHub repository. Verify host key fingerprints. Do not use a personal SSH key or a broad GitHub token on the server.

## 2. Choose the deployment layout

The safest supported layout keeps the Laravel project outside the web root and points the website at its `public/` directory. That is the recommended production setup below.

For a simpler shared-hosting upload workflow, the complete project can instead be uploaded or extracted directly into the domain's `public_html`, then installed at `/install`. The repository root `.htaccess` forwards requests into `public/` and blocks direct requests for Laravel source, dependencies, configuration and secrets. This shortcut is supported only when Apache processes every request and permits `.htaccess` overrides (`mod_rewrite` and `AllowOverride`); confirm this with the hosting provider. `.htaccess` does not configure Nginx. If Nginx can serve files directly, a web-server deny rule must block dotfiles and project paths such as `/app`, `/vendor`, `/storage`, `/bootstrap`, `/config`, `/database`, `/routes`, `/resources`, `/tests`, `/docs`, and root files like `/composer.json` before using this layout. Verify these paths return 403/404 and never file contents. If you cannot verify that, use the safer layout below.

To prepare a single uploadable ZIP from a Windows development machine, commit the reviewed release and run `powershell -ExecutionPolicy Bypass -File .\scripts\package-release.ps1`. It builds production PHP dependencies and frontend assets, then writes `dist/siyazpos-router-<version>.zip`. For the Apache-compatible upload layout, extract it directly into the domain's `public_html`; otherwise extract it into a private app directory and point the domain at its `public/` folder. The ZIP contains no `.env`, Git metadata, local keys or logs. The script requires Git, Composer, npm and tar locally; 7-Zip is optional and speeds up ZIP creation. For a tagged release, the ZIP name uses the tag; otherwise it uses the current commit identifier. Do not package uncommitted changes.

Even in the upload workflow, create the domain, enable HTTPS, choose a supported PHP version, create an empty database/user, and grant the PHP account write access to `storage/` and `bootstrap/cache/` before opening `/install`. The wizard configures the application and database; it cannot create the hosting account, web-server virtual host, TLS certificate, or database itself.

## 3. Clone into a private release directory

Connect as the Hestia domain owner, not root. Use a private directory outside the public web root, for example:

```sh
mkdir -p ~/apps
cd ~/apps
git clone --branch main --single-branch git@github.com:YOUR_ORG/YOUR_ROUTER_REPOSITORY.git router
cd router
```

Use the actual repository URL and approved release branch. In this recommended layout, keep `.git`, source, `vendor`, `.env`, storage, backups, and signing keys outside the web document root.

## 4. Point the website only at Laravel `public/`

Laravel's document root must be the repository's `public` directory. Configure the Hestia Nginx/Apache web template so requests for the domain are served from `/home/WEB_USER/apps/router/public` (using the actual Hestia user and path). Hestia layouts can differ: verify the generated virtual host configuration and ensure its public root resolves to that directory. Do not point the domain directly at the repository root in this layout. Do not make a symlink that allows browsing source or `.env`.

After applying the web template, verify that `/` serves the Laravel app and direct requests for `/.env`, `/vendor/`, `/.git/`, `/storage/logs/`, and `/bootstrap/` do not reveal files. Hestia's PHP-FPM user needs write access to `storage/` and `bootstrap/cache/` only; use the actual service/group model on the host rather than `chmod -R 777`.

## 5. Install dependencies and configure secrets

Install PHP dependencies without development packages:

```sh
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
```

Open `https://router.example.com/install` and enter the canonical HTTPS Router URL, the database host/port/name/user/password, and the initial super administrator name/email/password. The database and dedicated database user must already exist in HestiaCP, and the user must have schema privileges on that empty database. Submit the form only over HTTPS.

The installer creates a protected `.env`, a unique `APP_KEY`, a per-install RSA provisioning key pair under `storage/app/private/provisioning`, secure database-backed sessions/cache, the database schema, and the initial active `super_admin`. The initial administrator must enroll in MFA at first sign-in. After successful installation `/install` is disabled. The installer does not configure DNS, TLS, host firewall, scheduler, backups, or distribute the generated public signing key to POS/client verifiers.

Keep `.env` and the generated private key outside the public document root and readable only by the PHP service account. Back them up separately in encrypted storage. Never commit `.env` or print it into deployment logs. See [PRODUCTION-CONFIGURATION.md](PRODUCTION-CONFIGURATION.md) for proxy, session, signing key, scheduler, and operations settings.

## 6. Initialise and verify

After the installer reports success, verify the application from the private directory:

```sh
php artisan config:clear
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

The installer already creates the initial super administrator; no default credentials are seeded. Sign in over HTTPS, enroll the super administrator in MFA, and store recovery codes securely. Do not disable MFA to simplify setup.

The storage link is only needed for assets intentionally served from Laravel's public storage disk; product/customer POS uploads do not belong on Router. Inspect the target before creating a link and do not expose private storage.

Run `php artisan route:list`, `php artisan schedule:list`, and the test suite in CI before a release. Ensure `schedule:list` includes the endpoint health check and history pruning tasks. Make a real HTTPS request and confirm that secure cookies, production error pages, and HSTS behave as expected.

## 7. TLS, Cloudflare and network controls

- Use Hestia/Let's Encrypt or another managed certificate, renew automatically, and test renewal before launch.
- If using Cloudflare, choose Full (strict) TLS. Keep the origin certificate valid and configure the origin firewall for Cloudflare address ranges if possible. Do not treat Cloudflare as a substitute for application HTTPS or host firewall rules.
- Configure Laravel trusted proxies only for the known reverse proxy/CDN addresses. Do not accept forwarded host/protocol/IP headers from arbitrary clients. Validate the perceived request scheme and client IP at the origin.
- Allow inbound HTTP/HTTPS only as required, SSH only from operator networks, and no public database/cache ports. Restrict outbound traffic where practical while allowing DNS, HTTPS health checks to approved public POS endpoints, package/security updates, and required mail/monitoring.

## 8. Scheduler, queue and monitoring

Add one cron entry for the Hestia web user to run Laravel Scheduler every minute:

```cron
* * * * * cd /home/WEB_USER/apps/router && php artisan schedule:run >> /dev/null 2>&1
```

Check command path, PHP binary and permissions for the selected host. The current scheduled tasks do not require a queue worker; `QUEUE_CONNECTION=sync` is sufficient. If queued jobs are added later, choose a durable queue, configure a supervised worker, and restart it after each release.

Monitor HTTPS availability, Laravel/PHP-FPM and web server errors, disk space, database capacity, cron execution, backup completion, TLS expiry, and repeated authentication/activation failures. Restrict logs and alerts. Do not include activation codes, device tokens, passwords, MFA data or private-key contents in alert payloads.

## 9. Safe application updates

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

- [ ] Web document root is the Laravel `public/` directory, or Apache processes the root rewrite and requests for every project/source path are verified as blocked.
- [ ] `.env`, source, logs, backups and signing private keys are not web accessible.
- [ ] Production HTTPS, `APP_DEBUG=false`, encrypted/secure sessions and HSTS are verified.
- [ ] Database and deploy SSH access are least privilege and network restricted.
- [ ] Super administrator exists, MFA is enrolled, and recovery material is stored securely.
- [ ] Signing keys are loaded from protected files and public key configuration matches the issuer.
- [ ] Scheduler runs once per minute; monitoring and encrypted backups are active.
- [ ] Rollback path and tested restore procedure are available before accepting production usage.
