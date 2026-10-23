# Client App Version Settings

Super admins manage Windows and Android release settings at **Settings** (`/admin/settings`). Each platform has a latest version, minimum supported version, HTTPS download URL, mandatory-update flag, and short release notes. The minimum supported version cannot be newer than the latest release. Changes are audited with the actor, version values, download host, and mandatory-update state; release note text and full URLs are not copied into audit metadata.

Authenticated device config (`GET /api/v1/device/config`) includes the platform's `latest_version`, `minimum_supported`, `recommended` (same value as latest for compatibility), `download_url`, `mandatory_update`, and sanitized `release_notes`. The response remains scoped to the authenticated device and is marked `no-store`.

The dashboard counts active devices with an unreported or older app version as outdated and lists up to six. Disabled and revoked devices are excluded. This management data does not download, install, or force an update; the client app decides how to act on the config fields.
