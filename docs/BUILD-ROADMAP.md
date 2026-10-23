# SiyazPOS Router Build Roadmap

This is the source of truth for the remaining structured build. Implement phases in order, inspect existing code before each phase, avoid duplicates, preserve existing behavior, test each phase, update `docs/BUILD-STATUS.md`, review changes, and commit only when the worktree/staging scope is safe. Do not deploy or push without explicit authorization.

## Completed

- Phase 1 â€” Project foundation
- Phase 2 â€” Core database/domain architecture
- Phase 3 â€” Admin authentication
- Phase 4 â€” Admin dashboard shell
- Phase 5 â€” Customer management
- Phase 6 â€” Store management
- Phase 7 â€” Endpoint health checking
- Phase 8 â€” Activation code system

## Remaining phases
## Phase 9 â€” PUBLIC DEVICE ACTIVATION API â€” COMPLETED
==================================================

Implement:

POST /api/v1/activate

Example request:

{
  "store_code": "ASIAHUD",
  "activation_code": "5381-9427",
  "device_id": "SYP-K7P2-93XF",
  "device_name": "Till 1",
  "platform": "windows",
  "app_version": "1.0.0"
}

Validate:

- request structure
- customer active
- store exists and active
- activation code valid
- activation code not expired
- activation code not revoked
- usage available
- platform valid
- device limits respected
- endpoint valid
- licence allows registration once licensing exists

On success:

- create/prepare Device safely
- consume activation code atomically
- issue short-lived provisioning token
- return only:
  - business display name
  - store display name
  - endpoint URL
  - device reference
  - provisioning token
  - expiry

Requirements:

- no sensitive credentials in response
- public errors should avoid customer/store enumeration
- strict rate limiting
- concurrency-safe transaction logic
- meaningful tests

==================================================
## Phase 10 â€” SIGNED PROVISIONING TOKENS â€” COMPLETED
==================================================

Implement asymmetric signed provisioning tokens.

Architecture:

- Gateway owns PRIVATE signing key.
- Customer POS installations receive only PUBLIC verification key.
- Private key must never be committed.

Token claims:

- issuer
- store identifier
- device identifier
- issued_at
- expires_at
- jti / nonce
- purpose = device_provisioning
- signing key identifier if useful

Approximate expiry:
5 minutes.

Requirements:

- tamper-resistant
- no credentials in URLs
- future key rotation support
- secure environment/key loading

Create/update:

`docs/DEVICE-PROVISIONING.md`

Tests:

- valid token
- expired token
- tampered claims
- wrong key
- wrong purpose

Do not implement the customer POS repository in this repo.

==================================================
## Phase 11 â€” DEVICE MANAGEMENT â€” COMPLETED
==================================================

Implement admin device management.

Pages:

/admin/devices
/admin/devices/{device}

List:

- name
- Device ID
- customer
- store
- platform
- OS version
- app version
- last seen
- status

Filters:

- customer
- store
- platform
- status
- recently seen / offline

Detail:

- Device ID
- name
- customer
- store
- platform
- OS
- app version
- registration date
- last seen
- last IP
- status
- recent activity
- licence/store context

Actions:

- rename
- disable
- reactivate
- revoke
- move to another Store belonging to SAME customer only

DISABLED = temporary block.

REVOKED = permanent credential invalidation.

Audit all actions.

Add tests.

==================================================
## Phase 12 â€” DEVICE CREDENTIAL SYSTEM â€” COMPLETED
==================================================

Implement secure long-lived device authentication.

Requirements:

- high-entropy generated token
- plaintext returned only on issuance/rotation
- only secure hash stored server-side
- token bound to Device
- rotation supported
- revocation supported
- disabled/revoked devices cannot authenticate
- no credentials in URLs
- redact tokens from logs/errors

Create reusable authentication middleware/service.

Tests:

- valid token
- invalid token
- disabled device
- revoked device
- rotation

==================================================
## Phase 13 â€” DEVICE HEARTBEAT + CONFIG API â€” COMPLETED
==================================================

Implement:

POST /api/v1/device/heartbeat

Accept safe telemetry:

- app_version
- os_version
- platform

Record:

- last_seen_at
- request IP
- current versions

Do not collect unnecessary personal/device data.

Implement:

GET /api/v1/device/config

Return minimal configuration:

- business name
- store name
- endpoint URL
- device name/status
- licence summary
- minimum/recommended app version
- maintenance notice if configured

No secrets.

Rate limit.

Add tests.

==================================================
## Phase 14 â€” LICENSING FOUNDATION â€” COMPLETED
==================================================

Implement customer-level licensing.

Gateway = central licence authority.

Customer POS VPS = local licence cache/enforcer.

Licence states:

- trial
- active
- past_due
- grace
- restricted
- suspended
- cancelled

Support:

- plan code/name
- starts_at
- valid_until
- grace_until
- max_stores
- max_devices
- feature entitlements
- internal notes if appropriate

Admin:

/admin/licences
/admin/licences/{licence}

Customer detail should display licence state.

Admin actions:

- create licence
- change plan
- extend expiry
- mark past due
- configure grace
- restore active
- restrict
- suspend
- cancel

Audit changes.

No Stripe yet.

Add tests.

==================================================
## Phase 15 â€” SIGNED LICENCE TOKENS â€” COMPLETED
==================================================

Use asymmetric signing infrastructure to implement signed licence documents.

Customer POS must be able to verify licence OFFLINE with public key.

Claims:

- customer identifier
- licence identifier
- plan
- status
- issued_at
- valid_until
- grace_until
- max_stores
- max_devices
- features
- jti
- signing key id/version

Architecture:

- customer POS caches latest valid signed licence
- no router call on every sale
- router outage does not immediately stop trading
- refresh every 12â€“24 hours as appropriate
- licence remains offline-verifiable
- document clock rollback considerations

Create:

`docs/LICENSING.md`

Document:

ACTIVE
â†’ PAST_DUE
â†’ GRACE
â†’ RESTRICTED

Recommended behaviour:

active:
normal

past_due:
warning

grace:
POS continues normally

restricted:
customer POS may restrict NEW sales, but owner must retain access to historical data/report/export

suspended:
manual serious state

Do not implement customer POS enforcement here.

Add tests.

==================================================
## Phase 16 â€” LICENCE REFRESH API â€” COMPLETED
==================================================

Implement secure API for authorised customer/device context to retrieve latest signed licence.

Requirements:

- authenticated
- scoped to correct customer/store
- return current signed licence
- do not expose billing/admin secrets
- clear state
- sensible rate limiting

Add tests.

==================================================
## Phase 17 â€” LICENCE LIMIT ENFORCEMENT â€” IN PROGRESS
==================================================

Integrate limits with:

- stores
- device provisioning

Enforce:

- max_stores
- max_devices

Rules:

- do not automatically disable existing resources when limit reduced
- show over-limit state
- block NEW registrations as appropriate

Where both Store.max_devices and Licence.max_devices exist:
use/document the most restrictive applicable limit unless existing architecture strongly requires another explicit rule.

Tests:

- allowed registration
- store max reached
- customer device max reached
- expired licence
- grace licence
- existing over-limit customer

==================================================
## Phase 18 â€” EMERGENCY LICENCE EXTENSIONS â€” PENDING
==================================================

Implement controlled temporary extension:

- 1 day
- 3 days
- 7 days
- custom date for super_admin

Require:

- reason
- actor
- expiry
- audit entry

Do not overwrite licence history silently.

Signed licence must reflect effective extension/grace.

Tests.

==================================================
## Phase 19 â€” AUTOMATED HEALTH MONITORING â€” PENDING
==================================================

Extend existing manual health checking to scheduled checks.

Use Laravel scheduler/queues appropriately.

Requirements:

- default around every 5 minutes
- no overlapping checks per Store
- bounded concurrency
- preserve SSRF protection
- strict timeouts
- sensible history retention
- prevent uncontrolled DB growth

System Health should show:

- online
- offline
- degraded/slow if useful
- response time
- last success
- POS version
- API version

No external alerts yet.

Add tests.

==================================================
## Phase 20 â€” APP VERSION MANAGEMENT â€” PENDING
==================================================

Implement Windows/Android client version management.

Per platform:

- latest version
- minimum supported version
- update/download URL
- mandatory update flag
- release-note summary

Device config API returns safe version data.

Admin dashboard highlights outdated devices.

Do not build client updater.

Add tests.

==================================================
## Phase 21 â€” ADMIN USERS + ROLES â€” PENDING
==================================================

Implement complete admin management.

Roles:

SUPER_ADMIN
- full access

SUPPORT
- customers
- stores
- devices
- activation codes
- health
- view licences
- restricted from highly sensitive system/security actions

READ_ONLY
- view only

Implement:

- list
- create
- edit
- enable/disable
- role changes
- password reset/change as appropriate

Prevent disabling/removing the last usable super admin.

Audit sensitive actions.

Authorization tests.

==================================================
## Phase 22 â€” ADMIN MFA â€” PENDING
==================================================

Implement TOTP MFA.

Requirements:

- setup
- QR/secret provisioning
- confirmation
- login challenge
- recovery codes
- secure reset/disable
- TOTP secret encrypted at rest
- recovery codes hashed
- rate limit MFA attempts

Require MFA for super_admin.

Document recovery.

Tests.

==================================================
## Phase 23 â€” AUDIT SYSTEM REVIEW â€” PENDING
==================================================

Ensure consistent audit logging.

Audit:

ADMIN
- key security/login events
- role changes
- MFA reset

CUSTOMER
- create
- update
- status

STORE
- create
- update
- status
- endpoint changes

ACTIVATION
- generated
- revoked
- consumed

DEVICE
- provisioned
- disabled
- reactivated
- revoked
- moved
- credential rotated

LICENCE
- created
- changed
- past due
- grace extension
- restriction
- suspension

SETTINGS
- important changes

Record when appropriate:

- actor
- action
- resource
- timestamp
- IP
- safe before/after metadata

Never log secrets.

Build searchable/filterable Audit Logs UI.

Tests.

==================================================
## Phase 24 â€” CENTRAL SETTINGS â€” PENDING
==================================================

Implement safe non-secret settings:

- default activation expiry
- default activation uses
- health check frequency
- health history retention
- heartbeat expected interval
- minimum/latest Windows version
- minimum/latest Android version
- support email
- support phone
- default licence grace period
- maintenance notice

Do not store sensitive signing/API secrets in generic settings where environment/secret storage is more appropriate.

Validate and audit.

Tests.

==================================================
## Phase 25 â€” DEVICE ACTIVATION UI â€” PENDING
==================================================

Build minimal first-run activation UI suitable for:

- Windows WebView2
- Android WebView

Screen:

SIYAZ POS

Connect this device to your store

Store Code
[ ]

Activation Code
[ ]

[ Connect Store ]

Where wrapper integration provides it:
show Device ID.

Progress:

- Finding store
- Authorising
- Registering
- Connecting

Friendly errors:

- invalid details
- expired code
- account unavailable
- device limit reached
- endpoint unavailable
- connection failure

Never expose raw internal errors.

Touch-friendly and simple.

First-time setup only.

==================================================
## Phase 26 â€” SECURITY HARDENING â€” PENDING
==================================================

Perform dedicated security review.

Review:

- authentication
- MFA
- authorization
- IDOR
- CSRF
- XSS
- SQL injection
- mass assignment
- brute force
- activation guessing
- device tokens
- provisioning signatures
- licence signatures
- replay attacks
- token expiry
- private key storage
- key rotation
- SSRF
- unsafe redirects
- DNS rebinding considerations
- response-size limits
- rate limiting
- logs
- debug mode
- exception handling
- sessions/cookies
- security headers
- HTTPS
- CORS
- race conditions

Fix genuine issues.

Add regression tests.

Create/update:

`docs/SECURITY.md`

Include:
- production assumptions
- required secrets
- key rotation process
- security checklist

No actual secrets.

==================================================
## Phase 27 â€” UI POLISH â€” PENDING
==================================================

Polish admin UI only.

Brand:
SiyazPOS

Style:

- white/light grey
- navy
- blue/cyan accent
- clean SaaS style
- compact navigation
- good whitespace
- subtle borders
- restrained shadows
- responsive

Improve:

- Dashboard
- Customers
- Stores
- Devices
- Activation Codes
- Licences
- System Health
- Audit Logs
- Settings
- forms
- empty states
- confirmation dialogs
- status badges

Clearly distinguish:

- Active
- Offline
- Suspended
- Past Due
- Grace
- Restricted
- Revoked

Avoid clutter.

Run tests.

==================================================
## Phase 28 â€” FULL TEST PASS â€” PENDING
==================================================

Perform comprehensive production-readiness testing.

Prioritise:

- authentication
- MFA
- roles
- customers
- stores
- endpoint validation
- SSRF
- activation generation/expiry/revocation
- atomic activation consumption
- activation rate limiting
- device provisioning
- device credentials
- heartbeat/config
- provisioning signatures
- licence transitions
- licence signatures
- grace
- limits
- endpoint health
- audit logging
- secret leakage

Do not add meaningless coverage-only tests.

Run full suite.

Record:
- test count
- assertions if available
- failures
- unresolved risks

Update BUILD-STATUS.

==================================================
## Phase 29 â€” PRODUCTION CONFIGURATION â€” PENDING
==================================================

Prepare production configuration.

Review:

- APP_ENV production
- APP_DEBUG false
- secure cookies
- HTTPS
- Cloudflare/trusted proxies
- caches
- queues
- scheduler
- logging
- log rotation
- database config
- signing-key loading
- permissions
- rate limiting

Do not hardcode production secrets.

Maintain safe `.env.example`.

Create:

`docs/PRODUCTION-CONFIGURATION.md`

==================================================
## Phase 30 â€” VPS DEPLOYMENT GUIDE â€” PENDING
==================================================

Create:

`docs/PRODUCTION-DEPLOYMENT.md`

Target:

siyazposrouter.top

Environment:

- Ubuntu VPS
- HestiaCP
- Nginx/Apache via Hestia
- MySQL/MariaDB
- GitHub repository
- Laravel-compatible PHP
- Cloudflare may proxy domain

Document:

1. Hestia domain
2. database
3. SSH
4. GitHub deployment access
5. clone
6. Laravel public/document root
7. composer production install
8. production `.env`
9. APP_KEY
10. DB config
11. signing keys
12. migrations
13. permissions
14. storage link if required
15. optimize/cache
16. queues
17. scheduler
18. HTTPS
19. Cloudflare
20. backups
21. monitoring
22. safe updates
23. rollback
24. maintenance mode

No real credentials.

==================================================
## Phase 31 â€” SAFE DEPLOYMENT SCRIPT â€” PENDING
==================================================

Create safe deployment script.

Requirements:

- fail on errors
- verify repo/branch
- fetch/pull safely
- composer install --no-dev --optimize-autoloader
- maintenance mode if appropriate
- migrate --force
- rebuild caches
- restart queues if used
- leave maintenance
- clear output

Never:

- overwrite `.env`
- regenerate APP_KEY
- regenerate signing keys
- reset DB
- delete data
- force overwrite uncommitted production files

Document rollback.

Do not deploy production.

==================================================
## Phase 32 â€” BACKUP + RESTORE â€” PENDING
==================================================

Create:

`docs/BACKUP-RESTORE.md`

Cover:

- daily DB backups
- retention
- off-server copies
- encryption
- restoration
- restore testing
- private/signing key backup
- `.env` considerations
- disaster recovery

Avoid assuming paid providers unless needed.

==================================================
## Phase 33 â€” FINAL ARCHITECTURE REVIEW â€” PENDING
==================================================

Perform final review.

Confirm:

GATEWAY:
- customer/store directory
- provisioning authority
- device management
- licence authority
- health monitoring
- config/version distribution

CUSTOMER VPS:
- owns products
- owns sales
- owns stock
- owns staff/users
- owns business DB
- validates Gateway-signed provisioning token
- caches/verifies/enforces signed licence

WINDOWS/ANDROID:
- first activation through Gateway
- stores assigned endpoint securely
- normal POS traffic goes directly to customer VPS
- only periodic Gateway heartbeat/config/licence contact

Critical:

Gateway outage must NOT immediately stop already-provisioned, validly licensed customers from trading.

Review for:

- accidental Gateway dependency
- duplicated functionality
- insecure coupling
- missing tests
- incomplete docs
- inconsistent APIs
- architecture shortcuts
- unresolved security issues

Fix safe obvious problems.

Produce final readiness report.

==================================================
