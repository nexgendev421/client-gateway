# Administrator multi-factor authentication

SiyazPOS Router supports time-based one-time passwords (TOTP) from standard authenticator apps. TOTP secrets are encrypted using Laravel's application encryption key. Recovery codes are individually hashed and can each be used once. Secret-bearing setup and recovery responses use `Cache-Control: no-store`.

Super administrators must enrol before they can use the admin area by default. For local development only, set `ROUTER_REQUIRE_SUPER_ADMIN_MFA=false` to skip mandatory enrolment; production should keep it `true`. Other admin roles can enable MFA from **Security** in the admin header. Sign-ins for an enrolled account require an authenticator or unused recovery code. MFA challenges expire after ten minutes and are rate-limited per account and IP.

## Setup

1. Sign in with the admin password.
2. Scan the QR code in an authenticator app. If scanning is unavailable, enter the displayed setup key manually.
3. Enter the current six-digit authenticator code to confirm.
4. Save the one-time recovery codes in a secure password manager. The Router does not display them again.

## Lost authenticator access

Use one saved recovery code at the sign-in challenge. Each recovery code works once. Once signed in, use **Security → Reset authenticator or recovery codes** with the current password and a current authenticator or unused recovery code. Reset clears the existing secret and codes; complete setup again to restore MFA and receive a fresh set of recovery codes.

If every authenticator and recovery method is lost, an authorized super administrator must first disable the affected admin account, verify the operator out-of-band, then use a controlled database/admin recovery process to clear `mfa_secret`, `mfa_pending_secret`, `mfa_enabled_at`, and that user's `mfa_recovery_codes`. Re-enable the account only after confirming identity; a super administrator will be forced to enrol again at next sign-in. Record the incident through the organization's approved security process. Never copy secrets or recovery codes into audit metadata or support tickets.

## Key management

Back up and protect `APP_KEY`. Existing encrypted MFA secrets cannot be decrypted if the key is lost or changed. Rotate the key only through the documented application key rotation process, with an encrypted backup and a planned migration of encrypted values.
