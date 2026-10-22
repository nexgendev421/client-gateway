# Client Gateway / SiyazPOS Router

This repository is the central provisioning, routing, device-management, health-monitoring, and licensing service used by SiyazPOS.

## Core architecture

- Every customer has their own VPS, database, SiyazPOS installation, and POS endpoint/domain.
- The Gateway must not store customer sales, products, stock, receipts, cashier PINs, or normal POS transactional data.
- After provisioning, Windows and Android clients connect directly to the customer's POS endpoint.
- The Gateway must never proxy normal POS transaction traffic.
- A Gateway outage must not immediately stop already-provisioned, validly licensed shops from trading.

## Gateway responsibilities

1. Customers and stores/branches
2. Store codes and POS endpoint discovery
3. One-time activation codes
4. Windows/Android device provisioning and management
5. Device credentials, heartbeat, and configuration
6. POS endpoint health monitoring
7. Central licence authority, limits, grace periods, and signed licence documents
8. Signed provisioning tokens
9. App version management
10. Admin users, roles, and MFA
11. Audit logging and central non-secret settings
12. Production deployment and backup/recovery documentation

## Permanent development rules

- Inspect before changing. Do not duplicate existing functionality.
- Preserve working behaviour unless a requirement explicitly changes it.
- Follow the current Laravel architecture and conventions.
- Never commit `.env`, passwords, database credentials, API keys, private signing keys, activation codes, device tokens, or secrets.
- Never store plaintext activation codes or device credentials server-side, and never log secrets.
- Never expose raw internal exceptions, stack traces, or infrastructure details through public APIs.
- Use server-side validation, policies/authorization, database transactions where race conditions matter, and rate limiting where appropriate.
- Treat activation, provisioning, device authentication, licensing, and outbound POS endpoint requests as security-critical.
- Add meaningful tests for every phase. Fix failures before continuing to the next phase.
- Avoid unnecessary packages and over-engineering.
- Gateway downtime must not create an immediate dependency for normal POS sales.

## Autonomous workflow

When working from `docs/BUILD-ROADMAP.md`:

1. Read the current phase and inspect relevant implementation.
2. Implement only missing requirements; preserve established behaviour.
3. Add/update tests, run relevant tests, fix failures, then run regression tests.
4. Update `docs/BUILD-STATUS.md` with decisions, tests, risks, and next phase.
5. Review Git status and diffs for secrets and unintended changes.
6. Commit a completed phase when the working tree and staged scope are safe.
7. Continue to the next phase without asking whether to continue.

Do not push or deploy unless explicitly authorized. Stop only for a genuine blocker that cannot safely be inferred from the repository or environment; record it in the build status first.
