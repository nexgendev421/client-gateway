# Build Status

Current phase: 12 - Device Credential System

## Completed

- Phases 1-8: Foundation, domain schema, admin authentication, dashboard, customer/store administration, endpoint health checks, and activation-code administration.
- Phase 9: Public device activation API.
- Phase 10: Signed provisioning token issuer, verifier contract, and key operations documentation.
- Phase 11: Device directory and lifecycle management.

## In Progress

- Phase 12 - Device Credential System.

## Pending

- Phases 13-33. See [BUILD-ROADMAP.md](BUILD-ROADMAP.md) for ordered requirements.

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
- Implementation: Added a reusable RS256 verifier selecting public keys by `kid`, validating the signature, issuer, audience, purpose, identity, and time claims, with support for multiple trusted keys during rotation. Added [DEVICE-PROVISIONING.md](DEVICE-PROVISIONING.md) covering the contract, key handling/rotation, and replay prevention responsibilities.
- Verification: 5 focused verifier tests passed, 22 assertions; full suite passed with 57 tests and 349 assertions; SQLite `migrate:fresh`, targeted Pint, and `git diff --check` passed.
- Commit: `a133407` - `Add provisioning token verification and rotation docs`.
- Scope note: Customer POS verification and atomic `jti` replay storage belong in the separate customer POS repository. Production requires operator-provisioned keys.

### Phase 11 - Device Management

- Date: 2026-10-23.
- Implementation: Added the authenticated, role-aware device directory and detail page; search and customer/store/platform/status/recent-seen filters; rename, same-customer store move, disable, reactivate, and permanent revoke actions; per-device activity; and audit entries. State changes use row locks, revoked devices cannot be edited or moved, and Store/Customer device lists link to the detail page.
- Verification: 6 focused feature tests passed, 49 assertions; full suite passed with 63 tests and 398 assertions; targeted Pint, route-list inspection, and `git diff --check` passed.
- Commit: Pending.
- Known risks: “Offline” uses a temporary 24-hour last-seen threshold until Phase 13 heartbeat and Phase 24 settings define cadence. Credential authentication/revocation enforcement is Phase 12.
- Next: Implement Phase 12 device credentials.

### Build harness and repository guidance

- Date: 2026-10-23.
- Changes: Added root `AGENTS.md`, `BUILD-ROADMAP.md`, and this status file; updated `ARCHITECTURE.md` with the Gateway/customer VPS boundary and activation sequence.
- Commit: `4aa2760` - `Add autonomous build roadmap and repository guidance`.
