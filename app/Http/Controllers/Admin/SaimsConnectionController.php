<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Saims\SaimsClient;
use App\Services\Saims\SaimsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class SaimsConnectionController extends Controller
{
    /**
     * Connected, unreachable or token rejected, with the response time.
     * It never shows the token, and the check is kept for a few seconds so
     * reloading cannot use up SAIMS's 60 calls a minute.
     */
    public function show(Request $request, SaimsService $saims, SaimsClient $client): Response
    {
        abort_unless($request->user()?->can('roles.manage'), 403);

        // "Check again" forces a fresh call, but at most once every 5 seconds.
        if ($request->boolean('refresh') && Cache::add('saims:connection-recheck', true, 5)) {
            Cache::forget('saims:connection-status');
        }

        $url = (string) config('services.saims.url');

        return Inertia::render('Admin/Saims', [
            'status' => $saims->connectionStatus(),
            'settings' => [
                'enabled' => $client->isEnabled(),
                'configured' => $client->isConfigured(),
                // Host and port only. The token is never sent to the browser.
                'server' => $url !== '' ? (parse_url($url, PHP_URL_HOST).(parse_url($url, PHP_URL_PORT) ? ':'.parse_url($url, PHP_URL_PORT) : '')) : null,
                'timeout' => (int) config('services.saims.timeout'),
                'cache_seconds' => (int) config('services.saims.cache_seconds'),
            ],
        ]);
    }
}
