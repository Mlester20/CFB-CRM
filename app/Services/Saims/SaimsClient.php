<?php

namespace App\Services\Saims;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The one place that talks to SAIMS. Read-only: there is a get() and nothing else.
 *
 *  - base URL, bearer token, JSON, timeout from config('services.saims');
 *  - a small retry on connection errors only (never on an HTTP error answer);
 *  - every failure becomes a SaimsException with a friendly message;
 *  - successful GETs are cached for SAIMS_CACHE_SECONDS;
 *  - a local limiter stays under SAIMS's 60 calls a minute;
 *  - the token is never logged, returned or put in an exception.
 *
 * A future write or sync feature should add its own methods here (post/put on
 * the same pending request), reuse request() for the error mapping, and never
 * cache or retry non-idempotent calls.
 */
class SaimsClient
{
    /** SAIMS allows 60 calls a minute per token; stay a little below it. */
    private const CALLS_PER_MINUTE = 55;

    private const LIMITER_KEY = 'saims:outgoing';

    public function isEnabled(): bool
    {
        return (bool) config('services.saims.enabled');
    }

    public function isConfigured(): bool
    {
        return filled(config('services.saims.url')) && filled(config('services.saims.token'));
    }

    /**
     * GET {SAIMS_URL}/api/v1{path}. The path is always chosen by our own code
     * (SaimsService), never by the browser.
     *
     * @param  array<string, scalar>  $query
     * @return array<string, mixed> The decoded JSON body.
     *
     * @throws SaimsException
     */
    public function get(string $path, array $query = [], bool $cache = true): array
    {
        $this->ensureAvailable();

        $ttl = (int) config('services.saims.cache_seconds');
        $key = $this->cacheKey($path, $query);

        if ($cache && $ttl > 0 && is_array($hit = Cache::get($key))) {
            return $hit;
        }

        $body = $this->request($path, $query);

        if ($cache && $ttl > 0) {
            Cache::put($key, $body, $ttl);
        }

        return $body;
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>
     *
     * @throws SaimsException
     */
    private function request(string $path, array $query): array
    {
        if (RateLimiter::tooManyAttempts(self::LIMITER_KEY, self::CALLS_PER_MINUTE)) {
            throw $this->fail($path, SaimsFailure::RateLimited);
        }

        RateLimiter::hit(self::LIMITER_KEY, 60);

        try {
            $response = Http::baseUrl(rtrim((string) config('services.saims.url'), '/').'/api/v1')
                ->withToken((string) config('services.saims.token'))
                ->acceptJson()
                ->connectTimeout(min(3, max(1, (int) config('services.saims.timeout'))))
                ->timeout(max(1, (int) config('services.saims.timeout')))
                // Only a connection error is retried; an answer from SAIMS never is.
                ->retry(2, 200, fn ($exception) => $exception instanceof ConnectionException, throw: false)
                ->get($path, $query);
        } catch (ConnectionException) {
            throw $this->fail($path, SaimsFailure::Unreachable);
        }

        if ($response->successful()) {
            $body = $response->json();

            if (is_array($body) && array_key_exists('data', $body)) {
                return $body;
            }

            throw $this->fail($path, SaimsFailure::Failed, $response->status());
        }

        throw $this->fail($path, match ($response->status()) {
            401 => SaimsFailure::Unauthorized,
            403 => SaimsFailure::Forbidden,
            404 => SaimsFailure::NotFound,
            429 => SaimsFailure::RateLimited,
            default => SaimsFailure::Failed,
        }, $response->status());
    }

    /** @throws SaimsException */
    private function ensureAvailable(): void
    {
        if (! $this->isEnabled()) {
            throw new SaimsException(SaimsFailure::Disabled);
        }

        if (! $this->isConfigured()) {
            throw new SaimsException(SaimsFailure::NotConfigured);
        }
    }

    private function fail(string $path, SaimsFailure $failure, ?int $status = null): SaimsException
    {
        // Only the endpoint and the reason: no token, no query values, no response body.
        Log::warning('SAIMS request failed', [
            'endpoint' => $path,
            'failure' => $failure->value,
            'status' => $status,
        ]);

        return new SaimsException($failure, $status);
    }

    /**
     * The key holds a hash that depends on the token, so a different token
     * (with different abilities) never reads another token's cached data.
     *
     * @param  array<string, scalar>  $query
     */
    private function cacheKey(string $path, array $query): string
    {
        ksort($query);

        $token = hash('sha256', (string) config('services.saims.token'));

        return 'saims:'.hash('sha256', $token.'|'.$path.'|'.json_encode($query));
    }
}
