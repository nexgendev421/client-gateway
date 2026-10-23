# SiyazPOS Router backup and restore

The Router database contains customer/store directory data, registered device metadata, activation-code hashes, licences, health history, settings and audit events. It does not contain customer POS sales, products, stock or receipts. A backup is still security-sensitive: it contains personal business contacts and control-plane metadata.

## What to back up

Back up these items together as one deployment recovery set:

1. MariaDB database dump, including schema and data. The database is the authoritative copy of customer, store, device, licence, activation, settings and audit records.
2. Protected deployment configuration: `.env`, especially the exact `APP_KEY`, database connection configuration and issuer settings. Keep this as a separate encrypted secret backup, never beside an unencrypted SQL dump.
3. Provisioning and licence signing private keys, passphrases and key IDs, plus the public keys needed for verification. Export keys from the protected key location into an encrypted backup controlled by a separate key custodian. Retain old licence public keys for the lifetime of offline licences signed with them.
4. Any intentionally persistent files stored by the application. The Router should not be used for POS product images or customer transaction uploads. Laravel code and built assets should be recoverable from a reviewed Git release and reproducible build.
5. Hestia/web server configuration, PHP version/extensions, cron configuration and firewall/proxy configuration as infrastructure documentation or protected configuration exports.

The exact `APP_KEY` is required to read encrypted database values such as administrator MFA secrets. Do not generate a new key during restore. A key change requires a planned re-encryption/rotation procedure.

## Schedule and retention

- Create a database backup at least daily and before every migration or release that changes data.
- Copy backups off the VPS promptly to a separately secured location. A second directory on the same host is not an off-site backup.
- A practical starting policy is 14 daily, 8 weekly and 12 monthly restore points, adjusted to recovery objectives and storage limits. Retain incident/legal holds according to the operator's requirements.
- Encrypt in transit and at rest. Restrict backup read/write/delete permissions, use a separate off-site credential, and enable object lock/immutable retention when supported.
- Monitor backup job success, age, size changes, destination capacity, encryption and off-site copy status. Alert on missed backups and never include secrets in alert messages.

## Database backup example

Use a protected MariaDB option file owned by the backup service account (mode `0600`) instead of putting passwords on the command line. For example, `/etc/siyazpos-router/backup-client.cnf` can hold client connection settings. The placeholder names below must be replaced locally; the option file and resulting dump must not be committed.

```sh
umask 077
mkdir -p /secure/local-backup-staging
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
mariadb-dump --defaults-extra-file=/etc/siyazpos-router/backup-client.cnf \
  --single-transaction --quick --routines --triggers ROUTER_DATABASE \
  > "/secure/local-backup-staging/router-${STAMP}.sql"
test -s "/secure/local-backup-staging/router-${STAMP}.sql"
```

For InnoDB tables, `--single-transaction` provides a consistent snapshot without a long table lock. Coordinate schema changes with backups and verify the database engines. If non-transactional tables are introduced, use a database-level backup procedure that provides consistency. Restrict the DB account to the required database and dump privileges.

Encrypt the dump before sending it off-host. With `age`, for example:

```sh
age -r AGE_RECIPIENT_PUBLIC_KEY \
  -o "/secure/local-backup-staging/router-${STAMP}.sql.age" \
  "/secure/local-backup-staging/router-${STAMP}.sql"
```

Use a recipient public key in the command; keep its matching identity/private key in a separate protected recovery location. Verify the encrypted file, transfer it using authenticated TLS/SSH or an encrypted backup client, then remove the plaintext staging dump only after the encrypted off-site copy has been checked. Use a managed secret mechanism for the destination credential. Do not enable shell tracing around secret commands. Apply retention only to verified encrypted backups and test the retention logic outside the backup directory first.

## Restore procedure

1. Declare the incident, select the restore point, and confirm the encrypted backup's integrity/checksum and timestamp. Retrieve the decryption identity from the approved custodian.
2. Provision a clean, isolated Ubuntu host or staging clone. Patch it, restrict inbound access, install the compatible PHP/extensions, Composer, Node runtime if needed, and MariaDB client/server as appropriate.
3. Restore the reviewed Router source release. Do not point the public domain at an unverified restore. Keep the restored application in maintenance mode or behind an IP allowlist.
4. Restore the matching `.env`, exact `APP_KEY`, signing key set, key IDs and public-key configuration from the secret backup. Set tight file permissions before starting PHP-FPM. Check `APP_URL`, issuer and domain before allowing outbound health checks or issuing tokens.
5. Create an empty database with the expected charset/collation and least-privilege application user. Decrypt the SQL dump into a protected temporary location and import it. Use an option file for credentials; do not include a password in the command line.
6. Build Composer and Vite assets for the matching release, then run configuration caches. Do not run fresh migrations over an old restored snapshot until the target release and migration path have been reviewed.
7. Verify admin login and MFA, customer/store counts, device/licence records, activation and audit pages, signing-key loading, and scheduled tasks. Test a signed-token verification without exposing token content. Review logs for errors and verify no secret output.
8. Keep the service isolated until an operator approves recovery. If moving to a new host, update DNS/proxy/origin/firewall settings, then validate HTTPS, trusted-proxy behavior, secure cookies and HSTS before opening access.

Do not restore an old database over a live newer database without an explicit recovery decision; doing so can erase recent registrations, revocations, licence updates and audit records. Do not use `migrate:fresh`, `db:wipe`, or a blind migration rollback on production data.

## Restore testing and disaster recovery

- Perform a restore rehearsal at least quarterly and after major schema/key-management changes. Use an isolated staging environment with outbound access blocked or controlled.
- Record backup age, release/commit, dump/import duration, database integrity checks, application smoke checks, recovered key IDs, and the time required to restore.
- Confirm the restored site can decrypt MFA secrets using the recovered `APP_KEY`, can validate retained public signatures, and can sign only using the currently authorized private key.
- Test loss of the VPS, loss of the primary backup destination, and access to the secret/key custodian path. Keep emergency contacts and recovery instructions offline and access-controlled.
- Define recovery point and recovery time objectives with the service operator. Daily backups imply that changes since the latest backup may be lost; document this and consider more frequent database backups if the acceptable loss window is shorter.
- After recovery, rotate database, deployment and backup transport credentials if the incident may have exposed them. Treat signing-key rotation as a controlled trust transition, not a routine credential reset.

## Backup checklist

- [ ] Daily DB dump succeeds and is non-empty.
- [ ] Dump is encrypted before off-host transfer and integrity checked.
- [ ] `.env`/APP_KEY and RSA private keys have separate encrypted recovery copies.
- [ ] Retention, access controls, off-site destination and deletion protection are tested.
- [ ] Recovery code/release and host configuration can be reproduced.
- [ ] Quarterly restore rehearsal is recorded and meets the agreed recovery objective.
