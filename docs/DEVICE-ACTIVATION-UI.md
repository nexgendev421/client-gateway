# SiyazPOS device activation screen

The first-run screen is available at `/activate`. It posts the entered store and one-time activation codes to the existing `POST /api/v1/activate` endpoint. Activation failures use safe, general messages and never expose internal exceptions. The page and successful API response are marked `no-store`.

The page accepts optional, non-secret wrapper metadata in its query string:

- `device_id`: stable 8-64 character alphanumeric/hyphen device identifier.
- `device_name`: initial device name shown in the editable form.
- `platform`: `windows` or `android`.
- `app_version`: installed SiyazPOS client version.

The wrapper should provide a stable device ID, platform and actual installed version. When no ID is supplied, the page creates a random public device identifier and retains only that identifier in WebView local storage. When no version is supplied, the API receives `0.0.0` so version monitoring treats it as unknown/outdated. The activation code, provisioning token and device credential are never put in browser storage or query parameters.

## Native completion bridge

After the Router accepts activation, the page passes the response payload to the host in memory. The payload contains the assigned POS endpoint and short-lived provisioning token. It tries these bridges in order:

1. Windows WebView2: `window.chrome.webview.postMessage(JSON.stringify({type: 'siyazpos.activation.completed', payload}))`.
2. Android WebView: `window.SiyazPOSBridge.onActivationCompleted(JSON.stringify(message))`; expose this method from the app before loading the page.
3. Browser fallback: dispatch `siyazpos-activation-completed` with the payload as event detail.

The native app should validate the message type, exchange the provisioning token once at the Router credential endpoint, securely persist the returned device credential and POS endpoint, then send normal POS sales traffic directly to the assigned customer server. The Router does not proxy those sales requests. Do not log or display the payload token. A wrapper may report its subsequent POS connection result to its native UI; this activation page does not mistake the Router activation response for a completed POS health check.
