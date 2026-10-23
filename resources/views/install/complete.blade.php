<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Router installed</title>
    <style>
        body { min-height: 100vh; margin: 0; display: grid; place-items: center; padding: 20px; background: #f5f8fc; color: #0f172a; font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
        main { width: min(100%, 480px); padding: 28px; border: 1px solid #dbe3ee; border-radius: 14px; background: white; text-align: center; box-shadow: 0 8px 24px #0f172a0a; }
        .mark { display: grid; place-items: center; width: 48px; height: 48px; margin: auto; border-radius: 14px; background: #059669; color: white; font-size: 24px; }
        h1 { margin: 16px 0 8px; font-size: 24px; }
        p { color: #64748b; font-size: 14px; line-height: 1.6; }
        a { display: inline-block; margin-top: 12px; padding: 12px 20px; border-radius: 8px; background: #1d4ed8; color: white; font-size: 14px; font-weight: 700; text-decoration: none; }
    </style>
</head>
<body>
<main>
    <div class="mark" aria-hidden="true">✓</div>
    <h1>SiyazPOS Router is installed</h1>
    <p>Your database schema and super administrator are ready. Sign in over HTTPS and complete MFA enrollment. Keep your database credentials and signing keys backed up securely.</p>
    <a href="{{ route('login') }}">Continue to sign in</a>
</main>
</body>
</html>
