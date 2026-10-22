# Build Status

Current phase: 9 — Public Device Activation API

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

- Phase 9 — Public Device Activation API

The existing `POST /api/v1/activation-codes/redeem` validates a bounded-use activation code, enforces active customer/store state, expiry, optional intended device name, and use count in a transaction. It is rate-limited and audits consumption. It currently returns store routing details only. It does not follow the Phase 9 contract at `POST /api/v1/activate`, create a Device, enforce Store/customer device limits, or issue a signed provisioning token. Complete these before marking Phase 9 done.

## Pending

- Phase 10 onward — see [`BUILD-ROADMAP.md`](BUILD-ROADMAP.md) for the ordered requirements.

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
- **Commit:** Pending selective safety review and documentation-only commit.
- **Known risks:** The worktree already contains uncommitted application implementation; keep those changes out of a documentation-only commit.
