<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class DeviceActivationPageController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $deviceId = $request->query('device_id');
        if (! is_string($deviceId) || preg_match('/^[A-Za-z0-9][A-Za-z0-9-]{7,63}$/D', $deviceId) !== 1) {
            $deviceId = null;
        }

        $userAgent = (string) $request->userAgent();
        $platform = $request->query('platform');
        if (! in_array($platform, ['windows', 'android'], true)) {
            $platform = Str::contains($userAgent, 'Android') ? 'android' : 'windows';
        }

        $version = $request->query('app_version');
        if (! is_string($version) || preg_match('/^[A-Za-z0-9][A-Za-z0-9.+_-]{0,99}$/D', $version) !== 1) {
            $version = '0.0.0';
        }

        $name = $request->query('device_name');
        if (! is_string($name) || trim(strip_tags($name)) === '') {
            $name = $platform === 'android' ? 'Android POS device' : 'Windows POS device';
        }
        $name = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $name) ?? '';
        $name = mb_substr(trim(strip_tags($name)), 0, 100);

        return response()->view('activation.start', [
            'deviceId' => $deviceId,
            'platform' => $platform,
            'appVersion' => $version,
            'deviceName' => $name,
            'versionReported' => $version !== '0.0.0',
        ])->header('Cache-Control', 'no-store, private')->header('Pragma', 'no-cache');
    }
}
