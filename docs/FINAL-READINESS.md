# SiyazPOS Router final readiness review

Review date: 2026-10-23

## Architecture boundary

- Router is the provisioning, licensing, device-management and health-monitoring control plane.
- Each customer POS remains on its own VPS, database and endpoint. Router has no product, stock, sale, receipt or cashier-PIN tables and does not proxy ordinary POS traffic.
- Activation returns a short-lived signed provisioning token; the customer POS validates it and registers the device. Router device credentials authenticate only Router control-plane calls.
- Licence documents are signed for offline verification and carry signed start/validity/grace dates. Provisioning tokens are short-lived and one-time exchanged. A Router outage alone must not invalidate an already cached, currently valid customer licence.
- Admin identity, role/MFA controls, audit trail, customer/store/device/licence records and endpoint health records stay within the Router trust boundary.

## Security and operations reviewed

- Activation codes and device credentials are stored as hashes, rate limited, and protected by expiry/use controls and transactional updates. Provisioning exchange enforces signature, issuer, audience, purpose, time claims and unique token ID.
- Outbound endpoint checks reject unsafe destinations, validate public DNS answers, pin addresses for the request, verify TLS, refuse redirects, use bounded timeouts, and cap response bodies.
- Admin sessions use CSRF protection, login throttling, authorization, disabled-account checks and MFA controls. Audit metadata is filtered to remove sensitive keys/values; public error responses are generic.
- Production guidance covers HTTPS/proxy trust, environment secrets, session protections, signing-key rotation, restricted filesystem permissions, scheduler, backups and restoration. `.env.example` contains placeholders only and no environment file or signing private key is tracked.
- Health checks and history pruning are scheduled. Production dependency audit reported no known Composer advisories at review time.

## Validation performed

- Full suite: 134 tests, 884 assertions, zero failures.
- `composer audit`: no security vulnerability advisories.
- Production Vite asset build passed.
- Artisan route listing and scheduled-task listing completed; health check and retention tasks are registered.
- Deployment helper passed `bash -n`; it was not run against production.
- `git diff --check` passed and the worktree was reviewed before the final documentation commit.

## Boundaries and remaining operator work

- This repository does not contain the separate customer POS server or Windows/Android client implementations. Their secure endpoint storage, signed provisioning verification, local licence cache/enforcement and direct-to-customer request routing must be verified in those repositories and in a staging integration test.
- Strict Content Security Policy is not enabled yet because the activation WebView handoff currently uses inline JavaScript. Add a nonce/hash-based script policy before enforcing CSP.
- Production hostname, TLS certificate, trusted proxy address list, secrets, database, private signing-key custody, backup destination, restore rehearsal and host firewall remain environment-specific operator tasks. No production VPS was configured or deployed in this build.
- The deployment helper performs migrations but cannot decide whether a particular schema migration is reversible. Review migration impact, take a backup, and use a release-specific rollback plan before each production update.

## Readiness conclusion

The Router repository is ready for a controlled staging deployment after the operator follows [PRODUCTION-CONFIGURATION.md](PRODUCTION-CONFIGURATION.md) and [PRODUCTION-DEPLOYMENT.md](PRODUCTION-DEPLOYMENT.md), provisions protected secrets/keys, creates the initial MFA-enrolled administrator, and validates integrations with the separate POS/client repositories. Production launch should follow a successful staging activation, device credential exchange/heartbeat, signed licence verification, health check, backup and restore rehearsal. No application-level architecture change remains from the roadmap review.
