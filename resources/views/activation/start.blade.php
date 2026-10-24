<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light">
    <title>Connect this device · SiyazPOS</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <main class="flex min-h-screen items-center justify-center px-4 py-8 sm:px-6">
        <section class="w-full max-w-lg">
            <header class="mb-6 text-center">
                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-blue-700 text-lg font-bold text-white">S</div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-700">SiyazPOS</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight">Connect this device to your store</h1>
                <p class="mt-2 text-sm text-slate-500">Enter the store code and one-time activation code provided by your store administrator.</p>
            </header>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                <div class="mb-5 rounded-xl bg-slate-50 p-3.5">
                    <div class="flex items-center justify-between gap-3"><span class="text-xs font-medium text-slate-500">Device ID</span><span id="platform-label" class="rounded-full bg-blue-50 px-2.5 py-1 text-[11px] font-medium capitalize text-blue-700">{{ $platform }}</span></div>
                    <code id="device-id" class="mt-1.5 block break-all font-mono text-sm font-semibold text-slate-800">Preparing device ID…</code>
                </div>

                <div id="error-message" role="alert" aria-live="assertive" class="mb-4 hidden rounded-lg border border-rose-200 bg-rose-50 px-3.5 py-3 text-sm text-rose-800"></div>
                <div id="success-message" role="status" aria-live="polite" class="mb-4 hidden rounded-lg border border-emerald-200 bg-emerald-50 px-3.5 py-3 text-sm text-emerald-800"></div>

                <form id="activation-form" class="space-y-4" autocomplete="off">
                    <div><label for="store-code" class="mb-1.5 block text-sm font-medium text-slate-700">Store code</label><input id="store-code" name="store_code" type="text" inputmode="text" autocapitalize="characters" spellcheck="false" maxlength="20" required class="min-h-12 w-full rounded-lg border border-slate-300 px-3.5 py-3 text-base uppercase tracking-wide outline-none focus:border-blue-600 focus:ring-4 focus:ring-blue-100" placeholder="For example, ASIAHUD"></div>
                    <div><label for="activation-code" class="mb-1.5 block text-sm font-medium text-slate-700">Activation code</label><input id="activation-code" name="activation_code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="9" required class="min-h-12 w-full rounded-lg border border-slate-300 px-3.5 py-3 text-center font-mono text-lg tracking-[0.22em] outline-none focus:border-blue-600 focus:ring-4 focus:ring-blue-100" placeholder="1234-5678"></div>
                    <div><label for="device-name" class="mb-1.5 block text-sm font-medium text-slate-700">Device name</label><input id="device-name" name="device_name" type="text" maxlength="100" required value="{{ $deviceName }}" class="min-h-12 w-full rounded-lg border border-slate-300 px-3.5 py-3 text-base outline-none focus:border-blue-600 focus:ring-4 focus:ring-blue-100"></div>
                    <p class="text-xs leading-5 text-slate-500">Platform: <span class="font-medium capitalize text-slate-700">{{ $platform }}</span>.@if(!$versionReported) The app version was not supplied by the device and will be reported as unknown.@endif</p>
                    <button id="connect-button" type="submit" class="flex min-h-12 w-full items-center justify-center rounded-lg bg-blue-700 px-4 py-3 text-base font-semibold text-white transition hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-200 disabled:cursor-wait disabled:opacity-60">Connect Store</button>
                </form>

                <ol id="progress-list" aria-live="polite" class="mt-5 hidden space-y-2 border-t border-slate-100 pt-4 text-sm">
                    @foreach(['Finding store', 'Authorising activation', 'Registering device', 'Connecting to store'] as $step => $label)
                        <li id="progress-{{ $step }}" class="flex items-center gap-2.5 text-slate-400"><span class="flex h-5 w-5 items-center justify-center rounded-full border border-current text-[10px]">{{ $step + 1 }}</span><span>{{ $label }}</span></li>
                    @endforeach
                </ol>
                <p class="mt-5 text-center text-xs text-slate-400">If you need help, contact your store administrator.</p>
            </section>
        </section>
    </main>

    <script>
        (() => {
            const suppliedId = @js($deviceId);
            const platform = @js($platform);
            const appVersion = @js($appVersion);
            const activationUrl = @js(route('api.v1.activate'));
            const storageKey = 'siyazpos.device-id.v1';
            const idPattern = /^[A-Za-z0-9][A-Za-z0-9-]{7,63}$/;
            const randomId = () => {
                const bytes = new Uint8Array(16);
                crypto.getRandomValues(bytes);
                return 'SYP-' + Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('').toUpperCase();
            };
            let deviceId = suppliedId;
            if (!deviceId) {
                try {
                    const storedId = localStorage.getItem(storageKey);
                    deviceId = storedId && idPattern.test(storedId) ? storedId : randomId();
                    localStorage.setItem(storageKey, deviceId);
                } catch (_) {
                    deviceId = randomId();
                }
            }
            document.getElementById('device-id').textContent = deviceId;

            const form = document.getElementById('activation-form');
            const button = document.getElementById('connect-button');
            const errorBox = document.getElementById('error-message');
            const successBox = document.getElementById('success-message');
            const progressList = document.getElementById('progress-list');
            const progressLabels = ['Finding store', 'Authorising activation', 'Registering device', 'Connecting to store'];
            const markProgress = (active, completed = false) => {
                progressLabels.forEach((_, index) => {
                    const item = document.getElementById(`progress-${index}`);
                    item.className = index < active || completed
                        ? 'flex items-center gap-2.5 text-emerald-700'
                        : index === active
                            ? 'flex items-center gap-2.5 font-medium text-blue-700'
                            : 'flex items-center gap-2.5 text-slate-400';
                    item.querySelector('span').textContent = index < active || completed ? '✓' : String(index + 1);
                });
            };

            form.addEventListener('submit', async event => {
                event.preventDefault();
                const hasNativeBridge = (window.chrome && window.chrome.webview && typeof window.chrome.webview.postMessage === 'function')
                    || (window.SiyazPOSBridge && typeof window.SiyazPOSBridge.onActivationCompleted === 'function');
                if (!hasNativeBridge) {
                    errorBox.textContent = 'Open this activation page inside the SiyazPOS app. A browser alone cannot finish connecting this device to your store.';
                    errorBox.classList.remove('hidden');
                    return;
                }
                errorBox.classList.add('hidden');
                successBox.classList.add('hidden');
                progressList.classList.remove('hidden');
                markProgress(0);
                button.disabled = true;
                button.textContent = 'Connecting…';
                const transitionTimer = window.setTimeout(() => markProgress(1), 450);

                try {
                    const response = await fetch(activationUrl, {
                        method: 'POST',
                        headers: {'Accept': 'application/json', 'Content-Type': 'application/json'},
                        cache: 'no-store',
                        body: JSON.stringify({
                            store_code: form.elements.store_code.value.trim().toUpperCase(),
                            activation_code: form.elements.activation_code.value.trim(),
                            device_id: deviceId,
                            device_name: form.elements.device_name.value.trim(),
                            platform,
                            app_version: appVersion,
                        }),
                    });
                    window.clearTimeout(transitionTimer);
                    if (!response.ok) {
                        const message = response.status === 429
                            ? 'Too many attempts. Wait a few minutes, then try again.'
                            : response.status === 503
                                ? 'The Router or store endpoint is temporarily unavailable. Please try again shortly.'
                                : response.status === 422
                                    ? 'The store is unavailable or these details could not be accepted. Check the codes or contact support.'
                                    : 'Connection failed. Check your network and try again.';
                        throw new Error(message);
                    }

                    const payload = await response.json();
                    markProgress(3, true);
                    document.getElementById('progress-3').className = 'flex items-center gap-2.5 font-medium text-blue-700';
                    document.getElementById('progress-3').querySelector('span').textContent = '…';
                    form.classList.add('hidden');
                    successBox.textContent = 'Activation succeeded. SiyazPOS is opening the secure connection to your store.';
                    successBox.classList.remove('hidden');

                    const message = {type: 'siyazpos.activation.completed', payload};
                    if (window.chrome && window.chrome.webview && typeof window.chrome.webview.postMessage === 'function') {
                        window.chrome.webview.postMessage(JSON.stringify(message));
                    } else if (window.SiyazPOSBridge && typeof window.SiyazPOSBridge.onActivationCompleted === 'function') {
                        window.SiyazPOSBridge.onActivationCompleted(JSON.stringify(message));
                    } else {
                        // The submit guard normally prevents browser-only activation.
                        // Keep this event for hosts that attach their bridge during handoff.
                        window.dispatchEvent(new CustomEvent('siyazpos-activation-completed', {detail: payload}));
                    }
                    form.elements.activation_code.value = '';
                } catch (error) {
                    window.clearTimeout(transitionTimer);
                    errorBox.textContent = error instanceof TypeError
                        ? 'Could not reach SiyazPOS Router. Check your internet connection and try again.'
                        : (error.message || 'Connection failed. Check your network and try again.');
                    errorBox.classList.remove('hidden');
                    progressList.classList.add('hidden');
                    button.disabled = false;
                    button.textContent = 'Connect Store';
                }
            });
        })();
    </script>
</body>
</html>
