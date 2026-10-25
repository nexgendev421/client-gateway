# Offline Licence Documents

SiyazPOS Router is the central licence authority. Each customer POS VPS owns the local enforcement and cached licence document. The Router signs current licence state; the customer POS verifies the signature locally and does not call the Router for every sale.

## Signed document

Router licence documents use compact JWT serialization and RS256, with the same external RSA signing key and `kid` rotation mechanism used for device provisioning. A document contains:

- `iss`: configured Router issuer.
- `aud`: `customer-pos`.
- `customer_id`, `licence_id`, `plan`, optional `plan_code`, and `status`.
- `starts_at`, `valid_until`, optional `grace_until` as Unix timestamps.
- `max_stores`, `max_devices`, and `features` from the licence metadata.
- `iat` and random `jti`.
- JWT header `alg=RS256`, `typ=JWT`, and the signing-key `kid`.

The document intentionally has no JWT `exp` claim. Its signature remains verifiable offline after a licence date passes so the local POS can distinguish expiry, grace, restriction, and cancellation and retain an auditable record. Access is governed by the signed status and dates, not by a short bearer-token lifetime. The customer POS must reject tokens with an untrusted key, wrong algorithm, wrong issuer/audience/purpose, malformed claims, or invalid signature.

Feature metadata carries entitlements only. Internal notes, billing secrets, passwords, device credentials, activation codes, and customer transactional data are never included. The Router form rejects sensitive feature key names and secret-like values, and the issuer filters them defensively. The receiving POS should still allow-list supported feature names and validate their value types.

## Offline behaviour

The customer POS should cache the most recent valid signed document and refresh from Router about every 12-24 hours when connectivity permits. It should not make a Router call during a sale.

Recommended local handling:

| State | Customer POS behaviour |
| --- | --- |
| `trial` | Normal operation within the signed trial dates. |
| `active` | Normal operation until `valid_until`. |
| `past_due` | Show an owner warning; continue trading while the signed validity window permits. |
| `grace` | Continue normal trading through `grace_until`. |
| `restricted` | The owner may restrict new sales, while retaining historical access, reporting, and export. |
| `suspended` | Treat as an explicit serious control state; show a clear owner/support message. |
| `cancelled` | Stop new licensed operations according to the signed cancellation state and effective dates; retain historical data and exports. |

If Router is unreachable, a customer with a cached, still-valid document continues operating according to that document. A Router outage alone must not immediately stop trading. If cached dates have passed and the customer is in a configured grace period, local POS can continue until `grace_until`; after that it applies its restricted/expired policy without deleting data or blocking owner access to historical records.

## Clock rollback

The customer POS should persist the greatest trusted wall-clock time it has observed alongside the licence cache. A backwards clock movement beyond a small tolerance should raise an owner-visible time warning and must not extend licence validity. Do not silently erase local history or lock the owner out of reports and exports because a clock changed. Prefer an authenticated time source during periodic connectivity, and document a support recovery process for hardware-clock failures.

## Key rotation and rollout

Use the Router key storage procedure in [DEVICE-PROVISIONING.md](DEVICE-PROVISIONING.md). Customer POS installations need only public verification keys. Publish the new public key to customer installations before the Router signs with its new `kid`. Keep old public keys available for cached documents and offline sites until the rollout policy guarantees those sites have refreshed or the signing key is retired under a documented trust policy. Never distribute a signing private key to a customer POS VPS.

## POS-server polling and provisioning

Each Router Store may have one active POS-server credential. An authorized admin issues it from the Store detail page; only the initial response displays the full value. Router stores a bcrypt hash and a short prefix for lookup. Rotation revokes the previous credential atomically. The plaintext credential belongs only in the corresponding POS server's protected `.env` file, never in the POS database as plaintext or in device apps.

The POS server calls `GET /api/v1/pos/licence` with `Authorization: Bearer <store-server-credential>`. Router derives both Store and Customer from the authenticated credential and refuses credentials for suspended Stores or Customers. A licensed response includes the current signed document with `store_id`, `store_code`, `refresh_after`, and bounded `offline_until` claims. The Router key's `kid` and RS256 signature are the trust anchor; there is no per-sale Router request. An unlicensed customer receives an explicit null document. Responses are no-store and rate-limited. Credentials are never included in response bodies or audit metadata.

The customer POS verifies and caches the document encrypted locally, refreshes every 15 minutes, and enforces the result on the POS server. It keeps the last verified licence through transient Router outages until the signed offline deadline. The default offline window is 72 hours (`ROUTER_LICENCE_OFFLINE_GRACE_HOURS`); do not shorten it without an operational recovery plan. The POS keeps historical pages and reports accessible while restricting new mutations in read-only/blocked states. Windows and Android apps still communicate directly with the POS; the Router is never in their normal sales path.

The existing `GET /api/v1/device/licence` endpoint remains for device-management clients, whose customer scope is derived from their authenticated Device. It is separate from POS-server polling.
