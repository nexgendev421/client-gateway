# SiyazPOS Router

The central provisioning, endpoint-directory, device-management, and licensing service for independently hosted SiyazPOS customer installations. Each customer keeps a separate VPS, database, and POS deployment. The Router is a control plane; it never proxies ordinary POS traffic or stores sales, stock, products, receipts, cashier PINs, or other transactional data.

The service is a Laravel 12 application with session-based administrator sign-in and the initial Router domain schema. Customer, device, licensing, and provisioning workflows are not implemented yet. See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) for the service boundaries.

## Requirements

- PHP 8.2 or newer with the PDO MySQL extension
- Composer 2
- MySQL 8 or MariaDB 10.6+
- PHP cURL extension for DNS-pinned endpoint health checks
- Node.js and npm for Tailwind/Vite assets

## Local development (Windows / XAMPP)

From the `router` directory:

```powershell
composer install
if (!(Test-Path .env)) { Copy-Item .env.example .env }
```

Create a local MySQL database named `siyazpos_router` and a development user, then set the local database credentials in `.env`. Generate a local application key and install the database schema:

```powershell
php artisan key:generate
php artisan migrate
php artisan router:admin:create
npm ci
npm run build
```

The administrator command prompts for a name, email, and confirmed password, then creates an active `super_admin`. No default account or password is seeded. Passwords must be at least 12 characters; use a unique production password.

Start the development server at `http://127.0.0.1:8001`:

```powershell
php artisan serve --host=127.0.0.1 --port=8001
```

Run the test suite with:

```powershell
php artisan test
```

Tests use an in-memory SQLite database; local application data uses MySQL/MariaDB. Never commit `.env`, application keys, signing keys, database credentials, or production endpoint credentials. `.env.example` contains placeholders only.
