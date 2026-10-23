<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Install SiyazPOS Router</title>
    <style>
        :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: #0f172a; background: #f5f8fc; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; padding: 32px 16px; }
        main { width: min(100%, 720px); margin: 0 auto; }
        header { margin: 0 auto 22px; text-align: center; }
        .mark { display: grid; place-items: center; width: 48px; height: 48px; margin: auto; border-radius: 14px; background: #1d4ed8; color: white; font-weight: 800; font-size: 22px; }
        .brand { margin: 12px 0 2px; color: #1d4ed8; font-size: 11px; font-weight: 800; letter-spacing: .16em; text-transform: uppercase; }
        h1 { margin: 5px 0; font-size: 26px; letter-spacing: -.03em; }
        .intro { margin: 6px 0 0; color: #64748b; font-size: 14px; }
        form, .notice { border: 1px solid #dbe3ee; border-radius: 14px; background: white; box-shadow: 0 8px 24px #0f172a0a; }
        form { padding: 24px; }
        fieldset { margin: 0; padding: 0; border: 0; }
        fieldset + fieldset { margin-top: 24px; padding-top: 20px; border-top: 1px solid #e2e8f0; }
        legend { margin-bottom: 12px; font-size: 15px; font-weight: 700; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .wide { grid-column: 1 / -1; }
        label { display: block; margin-bottom: 6px; color: #334155; font-size: 13px; font-weight: 600; }
        input { width: 100%; min-height: 42px; padding: 9px 11px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; color: #0f172a; font: inherit; font-size: 14px; }
        input:focus { outline: 3px solid #dbeafe; border-color: #2563eb; }
        .help { margin: 5px 0 0; color: #64748b; font-size: 12px; line-height: 1.5; }
        .notice { margin-bottom: 14px; padding: 12px 14px; color: #334155; font-size: 13px; line-height: 1.55; box-shadow: none; }
        .error { margin-bottom: 14px; padding: 12px 14px; border: 1px solid #fecdd3; border-radius: 8px; background: #fff1f2; color: #9f1239; font-size: 13px; }
        .error ul { margin: 0; padding-left: 18px; }
        button { display: block; width: 100%; min-height: 46px; margin-top: 22px; border: 0; border-radius: 9px; background: #1d4ed8; color: white; font: inherit; font-size: 15px; font-weight: 700; cursor: pointer; }
        button:hover { background: #1e40af; }
        @media (max-width: 560px) { body { padding: 22px 12px; } form { padding: 18px; } .grid { grid-template-columns: 1fr; } .wide { grid-column: auto; } h1 { font-size: 23px; } }
    </style>
</head>
<body>
<main>
    <header>
        <div class="mark" aria-hidden="true">S</div>
        <p class="brand">SiyazPOS Router</p>
        <h1>Set up your Router</h1>
        <p class="intro">Enter the VPS database details and create your first super administrator.</p>
    </header>

    <div class="notice"><strong>Before you continue:</strong> create an empty MySQL/MariaDB database and a dedicated database user in your hosting panel, and grant that user access to the database. Use this page over HTTPS. The installer creates the schema, application key, provisioning signing keys and admin account. MFA is required for the super administrator after sign-in.</div>

    @if ($errors->any())
        <div class="error" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ route('install.store') }}" autocomplete="off">
        @csrf
        <fieldset>
            <legend>Router address</legend>
            <div class="grid">
                <div class="wide"><label for="app_url">Public HTTPS URL</label><input id="app_url" name="app_url" type="url" required value="{{ old('app_url') }}" placeholder="https://router.siyazpos.com" autocomplete="url"><p class="help">Use the public address administrators and POS clients will connect to. Configure DNS and TLS first.</p></div>
            </div>
        </fieldset>

        <fieldset>
            <legend>Database connection</legend>
            <div class="grid">
                <div><label for="db_host">Database host</label><input id="db_host" name="db_host" required value="{{ old('db_host', '127.0.0.1') }}" autocomplete="off"></div>
                <div><label for="db_port">Port</label><input id="db_port" name="db_port" type="number" min="1" max="65535" required value="{{ old('db_port', '3306') }}" inputmode="numeric"></div>
                <div><label for="db_database">Database name</label><input id="db_database" name="db_database" required value="{{ old('db_database') }}" autocomplete="off"></div>
                <div><label for="db_username">Database username</label><input id="db_username" name="db_username" required value="{{ old('db_username') }}" autocomplete="username"></div>
                <div class="wide"><label for="db_password">Database password</label><input id="db_password" name="db_password" type="password" required autocomplete="new-password"><p class="help">The password is written to the private .env file and is never shown again.</p></div>
            </div>
        </fieldset>

        <fieldset>
            <legend>Super administrator</legend>
            <div class="grid">
                <div><label for="admin_name">Name</label><input id="admin_name" name="admin_name" required maxlength="255" value="{{ old('admin_name') }}" autocomplete="name"></div>
                <div><label for="admin_email">Email address</label><input id="admin_email" name="admin_email" type="email" required maxlength="255" value="{{ old('admin_email') }}" autocomplete="email"></div>
                <div><label for="admin_password">Password</label><input id="admin_password" name="admin_password" type="password" required minlength="12" autocomplete="new-password"><p class="help">Use at least 12 characters. MFA enrollment will be required at first sign-in.</p></div>
                <div><label for="admin_password_confirmation">Confirm password</label><input id="admin_password_confirmation" name="admin_password_confirmation" type="password" required minlength="12" autocomplete="new-password"></div>
            </div>
        </fieldset>

        <button type="submit">Install SiyazPOS Router</button>
    </form>
</main>
</body>
</html>
