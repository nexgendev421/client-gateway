# Build Status

Current phase: Complete (Phases 1-33)

## Completed

- Phases 1-8: Foundation, domain schema, admin authentication, dashboard, customer/store administration, endpoint health checks, and activation-code administration.
- Phase 9: Public device activation API.
- Phase 10: Signed provisioning token issuer/verifier contract and key operations documentation.
- Phase 11: Device directory and lifecycle management.
- Phase 12: Hashed device credentials with one-time provisioning exchange, authentication middleware, rotation, and revocation.
- Phase 13: Authenticated device heartbeat and config APIs.
- Phase 14: Customer-level licence administration and audit workflows.
- Phase 15: Shared RS256 signing service and offline-verifiable signed licence documents.
- Phase 16: Authenticated per-customer signed licence refresh API.
- Phase 17: Licence store/device limit enforcement and over-limit reporting.
- Phase 18: Controlled temporary emergency licence extensions.
- Phase 19: Scheduled SSRF-safe endpoint checks, per-store lock, and history pruning.
- Phase 20: Windows/Android app version administration, device config details, and outdated-device dashboard reporting.
- Phase 21: Admin user lifecycle, role-based operational access, audit events, and last-super-admin protection.
- Phase 22: Encrypted TOTP setup and sign-in challenge, hashed one-use recovery codes, mandatory super-admin MFA, throttling, account security screens, and recovery documentation.
- Phase 23: Consistent sign-in/security audit events, searchable and filterable super-admin audit log, recursive secret/token redaction, and before/after store endpoint host snapshots.
- Phase 24: Central non-secret settings administration for activation defaults, health frequency/retention, heartbeat expectations, licence grace, support contact and maintenance notices; existing app-version management remains the version editor.
- Phase 25: First-run touch-friendly activation page for Windows/Android WebViews, using the existing activation API and an in-memory native provisioning handoff.
- Phase 26: Added browser security headers, production-only HSTS, safer production session defaults, a quieter `.env.example`, and production security/key rotation guidance. Reviewed existing authentication/MFA, authorization, hashed activation/device credentials, signed-token replay protection, SSRF-safe health checks, rate limits, audit redaction, and safe error handling.
- Phase 27: Added shared semantic status badges to customer, store, device, activation-code, and licence directories; improved compact mobile navigation scrolling and keyboard focus.
- Phase 28: Full regression suite passed with 134 tests and 884 assertions. Composer audit reported no advisories; asset build, route list, scheduler list and diff whitespace checks passed.
- Phase 29: Added production configuration guide for HTTPS/proxy trust, sessions, shared rate-limit cache, database/log permissions, signing keys, scheduler and release caches. `.env.example` remains placeholder-only.
- Phase 30: Added Ubuntu/HestiaCP deployment procedure including isolated Laravel public root, production setup, TLS/Cloudflare, Scheduler, monitoring, releases and rollback.
- Phase 31: Added guarded `scripts/deploy.sh` for clean-branch fast-forward releases, dependencies/assets, migrations and cache rebuilds; verified syntax without deploying.
- Phase 32: Added encrypted backup, off-site retention, restore and disaster-recovery guidance for Router database, APP_KEY and signing keys.
- Phase 33: Reconciled architecture documentation with scheduled health monitoring and offline-verifiable licence documents; completed final readiness review.

## In Progress

- None; roadmap Phases 1-33 are complete.

## Pending

- None.

## Progress log

### Phases 1-8 - Foundation through activation codes

- Date: 2026-10-23.
- Implementation: Laravel 12 foundation; customer/store/device/credential/activation/licence/health/audit/settings schema; admin authentication and role groundwork; dashboard; customer/store management; manual SSRF-safe endpoint health checks; hashed bounded-use activation codes with expiry/revocation/audit and admin views.
- Verification: 49 tests passed, 304 assertions at baseline. SQLite migration, frontend build, and earlier code-style checks passed. Full repository Pint still identifies formatting in untouched `bootstrap/providers.php` and `config/auth.php`.
- Commits: `d285831` database architecture; `c4d9795` project foundation; `4aa2760` autonomous build docs and repository guidance.

### Phase 9 - Public Device Activation API

- Date: 2026-10-23.
- Implementation: `POST /api/v1/activate` validates store/customer/code/licence/device limits, atomically registers the device and consumes the code, rate limits attempts, and issues a short-lived provisioning token. Public errors avoid customer/store enumeration.
- Verification: 52 tests passed, 327 assertions; SQLite `migrate:fresh`, `npm run build`, targeted Pint, and `git diff --check` passed. A temporary RSA smoke test verified the actual issuer signature, purpose, and 300-second lifetime.
- Commit: `181e9d2` - `Implement Router admin foundation and device activation` (includes previously uncommitted Phase 3-9 application work).
- Operational requirement: Production activation returns a safe 503 until an operator configures a private signing key outside the repository.

### Phase 10 - Signed Provisioning Tokens

- Date: 2026-10-23.
- Implementation: Added a reusable RS256 verifier selecting public keys by `kid`, validating signature, issuer, audience, purpose, identity, and time claims, with multiple trusted keys for rotation. Added [DEVICE-PROVISIONING.md](DEVICE-PROVISIONING.md) covering verification and key management.
- Verification: 5 focused verifier tests passed, 22 assertions; full suite passed with 57 tests and 349 assertions; SQLite `migrate:fresh`, targeted Pint, and `git diff --check` passed.
- Commit: `a133407` - `Add provisioning token verification and rotation docs`.
- Scope note: Customer POS verification and atomic `jti` replay storage belong in the separate POS repository. Production requires operator-provisioned keys.

### Phase 11 - Device Management

- Date: 2026-10-23.
- Implementation: Added role-aware device directory/detail; search and customer/store/platform/status/recent-seen filters; rename, same-customer store move, disable, reactivate, permanent revoke, activity, and audit entries. State changes use row locks; revoked devices cannot be edited or moved.
- Verification: 6 focused tests passed, 49 assertions; full suite passed with 63 tests and 398 assertions; targeted Pint, route-list inspection, and `git diff --check` passed.
- Commit: `81bd0ef` - `Add device administration workflows`.
- Known limit: “Offline” uses a temporary 24-hour last-seen threshold until heartbeat cadence is configurable.

### Phase 12 - Device Credential System

- Date: 2026-10-23.
- Implementation: Added high-entropy `sypd_` bearer credentials, one-way hash storage, one-time provisioning-token exchange with hashed `jti` replay tracking, active-device/store/customer checks, authentication middleware, rotation/revocation, and cascading admin device revocation. Responses are `no-store`; credentials are accepted only in Authorization headers and never URLs.
- Verification: 6 focused tests passed, 45 assertions; full suite passed with 69 tests and 443 assertions; SQLite `migrate:fresh`, targeted Pint, route-list inspection, and `git diff --check` passed.
- Commit: `761dd16` - `Add secure device credential lifecycle`.
- Scope: Credentials authorize Router control-plane calls. POS sales traffic stays direct to customer VPS installations.

### Phase 13 - Device Heartbeat and Config API

- Date: 2026-10-23.
- Implementation: Added authenticated, per-device throttled heartbeat/config endpoints. Heartbeats validate app/OS versions and platform, then update last-seen time, request IP, and versions. Config returns assigned business/store/endpoint/device details, minimal licence data, platform version settings, and an optional sanitized maintenance notice.
- Verification: 4 focused tests passed, 34 assertions; full suite passed with 73 tests and 477 assertions; SQLite `migrate:fresh`, targeted Pint, route-list inspection, and `git diff --check` passed.
- Commit: `62ceac0` - `Add device heartbeat and config APIs`.
- Known limit: Version settings are read-only until Phase 20/24 administration; offline listing uses the temporary 24-hour threshold.

### Phase 14 - Licensing Foundation

- Date: 2026-10-23.
- Implementation: Added customer-level licence administration with plan code/name, status, date window, grace, store/device limits, JSON feature entitlements, internal notes, list/detail/create/edit pages, customer detail links, and audited expiry/status operations. Support/read-only roles can view; only super admins manage.
- Verification at phase commit: 5 focused tests passed, 58 assertions; full suite passed with 78 tests and 535 assertions; SQLite migration, Pint, route list, and diff checks passed. Phase 15 later added extra feature-secret rejection coverage.
- Commit: `91e9299` - `Add customer licence administration`.
- Scope note: Stripe and signed licence tokens were separate phases. Store-limit enforcement is Phase 17.

### Phase 15 - Signed Licence Tokens

- Date: 2026-10-23.
- Implementation: Extracted a shared RS256 JWT signer for provisioning and licence tokens. It loads the external private key/passphrase, enforces RSA keys of at least 2048 bits, includes configured `kid`, and never persists or logs key material. Added signed licence documents with customer/licence identifiers, plan, status, dates, limits, filtered features, `iat`, and random `jti`. Added [LICENSING.md](LICENSING.md) for offline verification, outage behavior, clock rollback, and key rotation.
- Verification: 4 focused signature tests passed, 32 assertions; full suite passed with 82 tests and 569 assertions; SQLite `migrate:fresh`, targeted Pint, and `git diff --check` passed. Tests generated ephemeral RSA keys outside the repository and verified real signatures.
- Commit: `eda06d1` - `Add offline signed licence documents`.
- Design note: Licence dates are signed claims rather than JWT `exp`, allowing offline verification to distinguish expiry/grace/restriction after dates pass. Customer POS enforcement remains in its separate repository.
- Next: Implement Phase 16 authenticated licence refresh API.

### Phase 16 - Licence Refresh API

- Date: 2026-10-23.
- Implementation: Added GET /api/v1/device/licence, authenticated by the registered device credential and scoped from that device to its customer. It returns the current signed document, clear status and validity/grace dates, or an explicit unlicensed state. Responses are no-store and share the per-device API rate limit. Signing failures return a safe service response.
- Verification: 4 focused feature tests passed, 21 assertions; full suite passed with 86 tests and 590 assertions; SQLite `migrate:fresh`, targeted Pint, route-list inspection, and `git diff --check` passed.
- Commit: `a0d28f2` - `Add authenticated licence refresh API`.
- Next: Implement Phase 17 licence limit enforcement.

### Phase 17 - Licence Limit Enforcement

- Date: 2026-10-23.
- Implementation: Centralized licence-capacity counts; store creation and customer moves now enforce `max_stores` under customer row locks. Device activation enforces per-store and customer-wide `max_devices`, with revoked devices freeing slots. Customer licence and store pages show each applicable limit and usage, including over-limit states; reducing a limit never disables existing stores or devices.
- Verification: 28 focused tests passed, 207 assertions; full suite passed with 91 tests and 612 assertions; SQLite `migrate:fresh`, changed-file Pint, route listing, and `git diff --check` passed.
- Commit: `7125a86` - `Enforce customer licence limits`.
- Next: Implement Phase 18 emergency licence extensions.

### Phase 18 - Emergency Licence Extensions

- Date: 2026-10-23.
- Existing behavior: Licence expiry updates already had a protected admin action, required reason, actor attribution, audit history, and signed-token fields sourced from the persisted licence.
- Implementation: Added controlled one-, three-, and seven-day extensions, plus an explicit custom expiry option. Extensions from an already expired licence count from the current time. The audit entry retains old/new expiry, old/new status, extension type, reason, and actor; custom expiry must be later than the current expiry or now, whichever is later.
- Verification: 6 focused tests passed, 76 assertions; full suite passed with 92 tests and 628 assertions; changed-file Pint and `git diff --check` passed.
- Commit: `ca72efd` - `Add emergency licence extensions`.
- Next: Implement Phase 19 automated health monitoring.

### Phase 19 - Automated Health Monitoring

- Date: 2026-10-23.
- Implementation: Added a five-minute scheduled command to check active POS endpoints sequentially (bounded concurrency of one) using the existing SSRF-safe transport, per-store cache locks shared with manual checks, 3-second connection and 6-second overall timeouts, and safe error records. Added daily configurable history pruning that retains the latest record for each store. System Health now shows last success and the last successful POS/API versions alongside current status and response time.
- Verification: 3 dedicated monitoring command tests passed, 15 assertions; full suite passed with 96 tests and 647 assertions; scheduler listing confirmed five-minute and daily tasks; changed-file Pint and `git diff --check` passed.
- Commit: `c6dbf43` - `Schedule safe POS health checks`.
- Next: Implement Phase 20 app version management.

### Phase 20 - App Version Management

- Date: 2026-10-23.
- Existing behavior: Device config already exposed minimum and recommended version strings from legacy system settings.
- Implementation: Added super-admin Windows/Android version settings for latest/minimum versions, HTTPS download URL, mandatory-update flag, and sanitized release notes. Preserved the legacy config fields and added latest/download/update/release-note fields. Dashboard counts and lists active devices with an unknown or older version. Admin changes are audited without copying release-note text or full download URLs. Added [APP-VERSIONS.md](APP-VERSIONS.md).
- Verification: 11 focused tests passed, 81 assertions; full suite passed with 101 tests and 681 assertions; changed-file Pint, route listing, and `git diff --check` passed.
- Commit: `97c9d9b` - `Manage client app versions`.
- Next: Implement Phase 21 admin users and roles.

### Phase 21 - Admin Users and Roles

- Date: 2026-10-23.
- Existing behavior: Authentication already stored role/status enums, denied disabled users, and granted super admins full access. Support and read-only users were previously view-only.
- Implementation: Added super-admin user search/list/create/edit/enable/disable, strong password set/reset, role/status changes, role-aware nav, and security audit events. Support can manage operational customer/store/device/activation workflows while remaining restricted from customer archive, permanent device revoke, licensing, app/system settings, audit tools, and admin accounts. Read-only remains view-only. Active super-admin row locks protect the last account from demotion/disable; self-disable is rejected. Added [ADMIN-ACCESS.md](ADMIN-ACCESS.md).
- Verification: 6 focused admin-user tests passed, 38 assertions; full suite passed with 107 tests and 723 assertions; changed-file Pint, route listing, and `git diff --check` passed.
- Commit: `76e14e2` - `Manage admin users and roles`.
- Next: Implement Phase 22 admin MFA.

### Phase 22 - Admin MFA

- Date: 2026-10-23.
- Implementation: Added encrypted TOTP secrets, local QR provisioning, authenticator setup/confirmation, MFA login challenge with TOTP replay prevention, ten individually hashed single-use recovery codes, secure password-plus-MFA reset/disable workflows, per-user/IP attempt throttling, and mandatory enrollment for super admins. MFA screens containing secrets or recovery codes are marked no-store. Added [ADMIN-MFA.md](ADMIN-MFA.md) recovery and key-management instructions.
- Verification: 9 focused MFA tests passed, 60 assertions; full suite passed with 116 tests and 783 assertions. `php artisan route:list --path=mfa`, changed-file Pint, and `git diff --check` passed.
- Commit: `edaac5a` - `Implement administrator MFA`.
- Next: Implement Phase 23 audit system review.
### Phase 23 - Audit System Review

- Date: 2026-10-23.
- Implementation: Added success, failure, and logout audit events for administrator sign-ins; extended audit metadata redaction to nested keys and sensitive patterns in values; store edits retain safe before/after field snapshots and endpoint hosts only. Replaced the Audit Logs placeholder with a super-admin-only search, actor, action, resource, and date filtered paginated event list.
- Verification: Audit administration tests passed (4 tests, 21 assertions); Store administration tests passed (17 tests, 125 assertions); full suite passed with 120 tests and 806 assertions. Changed-file Pint, audit route listing, and `git diff --check` passed.
- Commit: `2de270c` - `Complete audit log administration`.
- Next: Implement Phase 24 central settings.
### Phase 24 - Central Settings

- Date: 2026-10-23.
- Implementation: Added a super-admin central settings page and validated non-secret settings storage. Activation defaults prefill new code forms; the scheduled health runner applies a configurable interval, history pruning reads configurable retention, and device offline filtering uses three times the expected heartbeat interval. Device config now includes heartbeat interval, support contact, and a central maintenance notice. Grace transitions use the configured default unless a custom end date is supplied. Windows/Android version rules continue to use the existing app-version page, linked from central settings.
- Verification: Focused Router Settings, health monitoring, activation, licence, device, app-version, and System Health tests passed; full suite passed with 129 tests and 850 assertions. Changed-file Pint, settings route listing, scheduled task listing, and `git diff --check` passed.
- Commit: `69454ea` - `Add central Router settings`.
- Next: Implement Phase 25 device activation UI.
### Phase 25 - Device Activation UI

- Date: 2026-10-23.
- Implementation: Added `/activate` as a mobile/WebView first-run screen that calls the existing activation API, validates optional wrapper metadata, displays the device identifier and progress, and presents safe activation/rate-limit/service/network errors. Successful provisioning data is sent in memory over the WebView2 or Android bridge (with a browser event fallback); tokens and activation codes are never placed in URLs, displayed, logged, or persisted by the page. Added [DEVICE-ACTIVATION-UI.md](DEVICE-ACTIVATION-UI.md) with wrapper query parameters and native handoff requirements.
- Verification: 3 activation UI tests passed, 20 assertions; full suite passed with 132 tests and 870 assertions. `npm run build`, changed-file Pint, activation route listing, and `git diff --check` passed.
- Commit: `c593f9d` - `Add device activation WebView screen`.
- Next: Implement Phase 26 security hardening.
### Build harness and repository guidance

- Date: 2026-10-23.
- Changes: Added root `AGENTS.md`, `BUILD-ROADMAP.md`, and this status file; updated `ARCHITECTURE.md` with Gateway/customer VPS boundaries.
- Commit: `4aa2760` - `Add autonomous build roadmap and repository guidance`.

### Phase 26 - Security Hardening

- Date: 2026-10-23.
- Implementation: Added browser security headers globally, with HSTS limited to secure production requests. Production defaults enable encrypted sessions and secure cookies unless explicitly overridden. Changed `.env.example` to default debug off and info logging. Added [SECURITY.md](SECURITY.md) with production assumptions, secret handling, signing-key rotation, existing controls and release checklist.
- Review: Existing admin authentication/MFA, role checks, activation/device credential hashing, signed token expiry and single-use replay protection, SSRF-safe pinned health checks, rate limits, redacted audit logs and generic exception handling were inspected. No additional concrete defect was found in the reviewed paths. Strict CSP remains a future improvement because the activation page currently uses inline script handoff.
- Verification: Security header tests passed (2 tests, 14 assertions); full suite passed with 134 tests and 884 assertions. Changed-file Pint and `git diff --check` passed.
- Commit: `c0c674c` - `Harden production security defaults`.
- Next: Implement Phase 27 admin UI polish.

### Phase 27 - UI Polish

- Date: 2026-10-23.
- Implementation: Added a shared status badge with consistent distinct colours for active/online, grace/past due, suspended/restricted/offline, revoked/expired, and used states. Applied it across customer, store, device, activation-code, and licence lists. Improved mobile navigation snapping and keyboard focus visibility.
- Verification: Full suite passed with 134 tests and 884 assertions. `npm run build`, Pint, and `git diff --check` passed.
- Commit: `f23997e` - `Polish Router admin status and navigation UI`.
- Next: Perform Phase 28 full test pass.

### Phase 28 - Full Test Pass

- Date: 2026-10-23.
- Verification: Full PHPUnit suite passed: 134 tests, 884 assertions, zero failures. `composer audit` found no advisories. Production Vite build, route listing, scheduled-task listing, and `git diff --check` passed.
- Unresolved: Production values and host-level TLS/proxy configuration remain deployment-specific and are documented for the operator.
- Next: Prepare Phase 29 production configuration guidance.

### Phase 29 - Production Configuration

- Date: 2026-10-23.
- Implementation: Added [PRODUCTION-CONFIGURATION.md](PRODUCTION-CONFIGURATION.md), covering production environment values, TLS and proxy trust, sessions/cookies, shared cache/rate limits, database, logs, permissions, signing key loading/rotation, Scheduler, queue guidance, and Laravel release caches. No production secrets were added.
- Verification: Documentation reviewed against current Laravel configuration and the Phase 26 security controls; `.env.example` remains placeholder-only.
- Next: Create Phase 30 HestiaCP VPS deployment guide.

### Phase 30 - VPS Deployment Guide

- Date: 2026-10-23.
- Implementation: Added [PRODUCTION-DEPLOYMENT.md](PRODUCTION-DEPLOYMENT.md) for Ubuntu/HestiaCP, GitHub read-only deploy access, private checkout and Laravel public root, production setup/admin MFA, TLS/Cloudflare, Scheduler, monitoring, safe updates and rollback. It contains no real credentials.
- Verification: Reviewed documented commands against repository scripts, admin provisioning command, scheduler and architecture; documentation whitespace check passed.
- Next: Implement Phase 31 safe deployment script.

### Phase 31 - Safe Deployment Script

- Date: 2026-10-23.
- Implementation: Added `scripts/deploy.sh`: verifies app checkout, `.env`/APP_KEY, branch, clean tree and remote history; only fast-forwards; uses maintenance mode; installs Composer/npm dependencies, builds assets, runs forced migrations, rebuilds caches and requests queue restart. It never generates/replaces secrets, resets data or force overwrites changes. Deployment guide documents prerequisites, interruption and recovery.
- Verification: `bash -n scripts/deploy.sh` and `git diff --check` passed. No production deployment was performed.
- Next: Create Phase 32 backup and restore procedures.

### Phase 32 - Backup and Restore

- Date: 2026-10-23.
- Implementation: Added [BACKUP-RESTORE.md](BACKUP-RESTORE.md), covering daily DB dumps, retention/off-site encryption, protected APP_KEY/signing-key recovery, import procedure, isolated restore tests, and disaster recovery. No paid provider is assumed and no credentials are included.
- Verification: Documentation cross-checked against current schema, key configuration and deployment architecture; `git diff --check` passed.
- Next: Perform Phase 33 final architecture review.

### Phase 33 - Final Architecture Review

- Date: 2026-10-23.
- Review: Updated [ARCHITECTURE.md](ARCHITECTURE.md) to reflect scheduled endpoint checks and distinguish expiring provisioning tokens from offline-verifiable signed licence documents. Confirmed Router-owned schema contains only control-plane data, APIs do not proxy sales traffic, and the deployment/security/backup documentation is consistent with current implementation.
- Final report: [FINAL-READINESS.md](FINAL-READINESS.md) records the architecture boundary, controls, verification results, integration boundaries and remaining operator work.
- Verification: 134 tests and 884 assertions passed; Composer audit reported no advisories; production frontend build, artisan route/schedule listings, deployment script syntax, and `git diff --check` passed.
- Status: All roadmap phases are complete. No production deploy was performed.

### Local development setup follow-up

- Date: 2026-10-23.
- Finding: First local sign-in attempt revealed the XAMPP `.env` referenced a nonexistent Router database/account. After creating a dedicated local account, MariaDB 10.4 exposed legacy implicit `TIMESTAMP` defaults in required date fields and its `mysql.db` privilege table was marked crashed.
- Resolution: Backed up and repaired the local MariaDB privilege table, created a least-privilege `siyazpos_router` database account, changed required application dates in fresh-install migrations to `DATETIME`, and reran all 20 migrations successfully. Created a local super-admin account; no credentials are recorded here.
- Verification: Full suite passed with 134 tests and 884 assertions; `migrate:status` showed all migrations ran, MariaDB confirmed one active super admin and 20 application tables, and `http://localhost:8001/login` returned HTTP 200 with the login form.
