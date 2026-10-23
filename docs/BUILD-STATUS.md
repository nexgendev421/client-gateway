# Build Status

Current phase: 10 — Signed Provisioning Tokens

## Completed

- Phase 1 — Project foundation
- Phase 2 — Core database/domain architecture
- Phase 3 — Admin authentication
- Phase 4 — Admin dashboard shell
- Phase 5 — Customer management
- Phase 6 — Store management
- Phase 7 — Endpoint health checking
- Phase 8 — Activation code system

These phases are marked complete based on the user's project history and the implementation inspected in this repository. Some later-phase foundations exist (device and licence models, for example) but do not mean their workflows are complete.

## In Progress

- Phase 10 — Signed Provisioning Tokens

The Phase 10 RS256 token foundation was implemented alongside Phase 9 because activation must return the requested short-lived provisioning token. Remaining Phase 10 work is to formalize key generation/loading/rotation in dedicated documentation, define a reusable verifier contract without implementing the customer POS repository, and add tests for expiry, tampering, wrong key, and wrong purpose.

## Pending

- Phase 11 onward — see [`BUILD-ROADMAP.md`](BUILD-ROADMAP.md) for the ordered requirements.

## Progress log

### Phases 1–8 — Project foundation through activation codes

- **Date:** 2026-10-23
- **Implementation:** Laravel 12 foundation; core customer/store/device/credential/activation/licence/health/audit/settings schema and models; session-based admin authentication and role groundwork; dashboard; customer/store management; manual SSRF-safe endpoint health checks; hashed bounded-use activation codes with expiry, revocation, audit, and Store/global admin views.
- **Verification:** `php artisan route:list`; `php artisan test` — 49 tests passed, 304 assertions. Earlier project checks also passed Pint, `npm run build`, `git diff --check`, and a SQLite `migrate:fresh`.
- **Commit:** Existing Git history contains `d285831` for core database architecture and `c4d9795` for project foundation. Work for Phases 3–8 is currently present in the working tree and is not represented by a phase-specific commit.
- **Known gaps:** Device activation/provisioning is incomplete as detailed under Phase 9. Health checks are manual only. Device, licensing, user-management, and settings workflows are not yet built.
- **Next:** Complete Phase 9, then proceed to Phase 10.

### Build harness and repository guidance

- **Date:** 2026-10-23
- **Changes:** Added this status file, `BUILD-ROADMAP.md`, and root `AGENTS.md`; updated `ARCHITECTURE.md` with the Gateway/customer VPS boundary and first-run activation sequence.
- **Tests:** No implementation changed for this documentation checkpoint; baseline suite above passed.
- **Commit:** `4aa2760` — `Add autonomous build roadmap and repository guidance`.
- **Known risks:** The worktree already contained uncommitted application implementation; the guidance/roadmap was committed separately to avoid mixing it with that work.

### Phase 9 — Public Device Activation API

- **Date:** 2026-10-23
- **Implementation:** Added `POST /api/v1/activate` with strict request validation and five-per-minute IP rate limiting. Successful activation resolves the store case-insensitively, verifies active customer/store, active non-expired code, device name binding, safe endpoint format, eligible customer licence, duplicate Device ID, store capacity and customer device capacity. Device creation, activation use increment, usage/provisioning audit entries, and token issuance run within the activation-code transaction. Public failures avoid customer/store enumeration.
- **Token:** Returns business/store display names, endpoint URL, device reference, five-minute RS256 provisioning JWT, and expiry. Private key is loaded from an out-of-repository configured path. The client ID column now accepts a bounded 64-character identifier.
- **Verification:** Full suite — 52 passed, 327 assertions; `migrate:fresh` passed on SQLite; `npm run build` passed; targeted Pint passed; `git diff --check` passed. A temporary RSA smoke test verified the signature, purpose, and 300-second TTL using a key under the OS temp directory; no key was added to the repository.
- **Pint note:** Repository-wide `pint --test` still reports formatting in untouched `bootstrap/providers.php` and `config/auth.php`; changed files pass targeted Pint.
- **Commit:** Pending review of the existing application changes in the worktree.
- **Known risks:** Phase 9 uses the Phase 10 token issuer foundation. Production activation intentionally returns a safe 503 until a signing key path is configured. Customer POS verification and registration remain out of this repository's implementation scope.
- **Next:** Complete Phase 10 verification tests and `docs/DEVICE-PROVISIONING.md`.
