# Admin Access and Roles

| Capability | Super Admin | Support | Read Only |
|---|---:|---:|---:|
| View customers, stores, devices, activation codes, health, and licences | Yes | Yes | Yes |
| Manage customers and stores | Yes | Yes | No |
| Manage devices | Yes | Yes | No |
| Permanently revoke a device | Yes | No | No |
| Generate or revoke activation codes | Yes | Yes | No |
| Archive customers | Yes | No | No |
| Edit licences or app-version/system settings | Yes | No | No |
| Manage admin users and view sensitive audit tools | Yes | No | No |

Admin users can be created, edited, enabled, and disabled by a super admin. Passwords must be at least 12 characters and include letters and numbers. An administrator can set a new password from the user editor; password contents are hashed before storage and never included in audit metadata.

Admin accounts are disabled rather than permanently deleted. The final active super admin cannot be demoted or disabled, and an administrator cannot disable their own session account. Role, status, account creation, and password-reset actions are recorded in the audit log.
