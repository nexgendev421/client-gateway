# Build Status

Current phase: 11 - Device Management

## Completed

- Phases 1-8: Foundation, domain schema, admin authentication, dashboard, customer/store administration, endpoint health checks, and activation-code administration.
- Phase 9: Public device activation API.
- Phase 10: Signed provisioning token issuer, verifier contract, and key operations documentation.

## In Progress

- Phase 11 - Device Management (next implementation phase).

## Pending

- Phases 12-33. See [BUILD-ROADMAP.md](BUILD-ROADMAP.md) for ordered requirements.

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
- Verification: 5 focused verifier tests passed, 22 assertions. Full suite and formatting checks pending.
- Commit: Pending.
- Scope note: Customer POS verification and atomic `jti` replay storage belong in the separate customer POS repository. Production requires operator-provisioned keys.

### Build harness and repository guidance

- Date: 2026-10-23.
- Changes: Added root `AGENTS.md`, `BUILD-ROADMAP.md`, and this status file; updated `ARCHITECTURE.md` with the Gateway/customer VPS boundary and activation sequence.
- Commit: `4aa2760` - `Add autonomous build roadmap and repository guidance`.
