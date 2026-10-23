# Build Status

Current phase: 17 - Licence Limit Enforcement

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

## In Progress

- Phase 18 - App Version Management.

## Pending

- Phases 18-33. See [BUILD-ROADMAP.md](BUILD-ROADMAP.md) for ordered requirements.

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
- Commit: Pending.
- Next: Implement Phase 18 app version management.

### Build harness and repository guidance

- Date: 2026-10-23.
- Changes: Added root `AGENTS.md`, `BUILD-ROADMAP.md`, and this status file; updated `ARCHITECTURE.md` with Gateway/customer VPS boundaries.
- Commit: `4aa2760` - `Add autonomous build roadmap and repository guidance`.
