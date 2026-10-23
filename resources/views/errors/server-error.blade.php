<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Something went wrong · SiyazPOS Router</title></head>
<body style="margin:0;background:#f8fafc;color:#0f172a;font-family:system-ui,sans-serif">
    <main style="max-width:520px;margin:15vh auto;padding:32px;text-align:center">
        <div style="font-size:12px;font-weight:700;letter-spacing:.12em;color:#1d4ed8">SIYAZPOS ROUTER</div>
        <h1 style="margin:18px 0 8px;font-size:26px">We couldn’t complete that request</h1>
        <p style="margin:0;color:#64748b;line-height:1.6">Please try again in a moment. If the problem continues, contact your system administrator.</p>
        <a href="{{ route('login') }}" style="display:inline-block;margin-top:24px;color:#1d4ed8;text-decoration:none;font-weight:600">Return to sign in</a>
    </main>
</body>
</html>
