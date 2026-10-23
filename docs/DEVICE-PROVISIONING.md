# Device Provisioning Token Contract

SiyazPOS Router issues a short-lived, asymmetrically signed token after an activation code successfully registers a device. The separate customer POS installation verifies this token during first-run device registration. After provisioning, Windows and Android clients connect directly to their assigned POS endpoint; the Router does not proxy ordinary sales traffic.

## Token format

Tokens use compact JWT serialization and RS256 (RSA with SHA-256). The protected header contains `alg=RS256`, `typ=JWT`, and a `kid` that selects the trusted public key. Claims are:

| Claim | Meaning |
| --- | --- |
| `iss` | Configured Router issuer URL. |
| `aud` | `customer-pos`. |
| `store_code` | Assigned store identifier. |
| `device_id` | Client identifier registered by the Router. |
| `iat` | Unix issue time. |
| `exp` | Unix expiry time, normally five minutes after issue. |
| `jti` | Cryptographically random token identifier. |
| `purpose` | `device_provisioning`. |

The token contains no password, device credential, activation code, or customer transactional data. It is returned in the HTTPS activation response body and must never appear in a URL or logs.

## Verification contract for customer POS

The customer POS implementation is a separate project. Its first-run registration endpoint should:

1. Parse the JWT without trusting decoded values.
2. Require RS256, an accepted `kid`, the configured Router issuer, `aud=customer-pos`, and `purpose=device_provisioning`.
3. Verify the signature against the public key for that `kid` before acting on claims.
4. Enforce `iat` and `exp` with a small clock-skew allowance; reject expired and implausibly future-issued tokens.
5. Require non-empty `store_code`, `device_id`, and `jti`, then bind registration to those identities.
6. Atomically record `jti` as consumed with local device registration. Reject replayed identifiers.
7. Return a generic registration failure and never log the token.

The Router's `ProvisioningTokenVerifier` implements the token format, signature, issuer, audience, purpose, identity, expiry, and key-selection checks for reuse and contract testing. It does not implement a customer POS endpoint or replay storage; replay prevention belongs in the receiving POS installation.

## Key storage and rotation

- Generate the RSA private key in a trusted operator environment. Use at least 2048 bits; 3072 bits is preferred where operationally supported.
- Store the private key outside the repository with access limited to the Router service account. Protect backups and use an encrypted PEM passphrase where deployment can provide it securely.
- Configure `ROUTER_PROVISIONING_PRIVATE_KEY_PATH`, `ROUTER_PROVISIONING_PRIVATE_KEY_PASSPHRASE` when encrypted, and a unique `ROUTER_PROVISIONING_KEY_ID`. Never add private key material to `.env.example`, Git, logs, or generic database settings.
- Distribute the matching public key to customer POS installations through their separately managed deployment process. Public keys are not secrets, but their integrity is essential.
- For rotation, create a new key pair and `kid`; install the new public key on customer POS installations before switching Router signing to the new private key. Keep the old public key trusted for at least the maximum token lifetime plus clock skew and rollout margin. Then remove the old verification key and securely retire the old private key under the organization's retention policy.
- Configure `ROUTER_PROVISIONING_PUBLIC_KEYS` as a JSON object mapping accepted key IDs to public PEM file paths, for example `{"router-provisioning-v1":"/etc/siyazpos-router/keys/router-v1.pub"}`. Multiple keys can remain trusted during rotation.
- Keep Router and customer POS clocks synchronized. The verifier allows bounded clock skew (30 seconds by default, configurable up to 120 seconds).

Activation is unavailable if the Router cannot load its signing key. In production, configure and verify the key before issuing activation codes. The customer POS must continue serving already-provisioned, validly licensed stores if the Router is unavailable.

## Router device credentials

After the customer POS has received the provisioning token, the client exchanges that token with `POST /api/v1/device/credentials` using an HTTPS `Authorization: Bearer` header. The Router verifies the RS256 token, confirms the active device and store binding, and atomically consumes the token's `jti` (only its SHA-256 hash is stored). It returns a new long-lived `sypd_` bearer credential once, with `Cache-Control: no-store, private`. The plaintext credential must be stored securely by the client and must not be logged.

The Router stores only a one-way hash of the credential, a short lookup prefix, timestamps, and its device relationship. Authenticated device requests use the Authorization header; credentials are never accepted in URLs. Credentials authenticate only while the linked device, store, and customer are active. `POST /api/v1/device/credential/rotate` revokes prior credentials and returns the replacement once. `POST /api/v1/device/credential/revoke` invalidates the current credential. Admin revocation of a Device also revokes all its credentials. These endpoints are rate-limited.

The customer POS separately validates and consumes the same provisioning token in its own registration database. Router-side `jti` consumption protects the credential exchange; local POS-side consumption protects local registration. The resulting Router credential is for control-plane APIs such as heartbeat and config, not for routing POS sales.

## Local development

Tests generate ephemeral RSA keys in the operating system temporary directory. For a local activation smoke test, create a development-only key pair outside the repository, configure the private-key variables and matching public-key map, and never reuse development keys in production.
