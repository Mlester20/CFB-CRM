<?php

namespace Tests\Feature\Saims;

use App\Services\Saims\SaimsClient;
use App\Services\Saims\SaimsException;
use App\Services\Saims\SaimsFailure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SaimsClientTest extends TestCase
{
    public const TOKEN = 'test-secret-token-123';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.saims' => [
            'enabled' => true,
            'url' => 'http://saims.test',
            'token' => self::TOKEN,
            'timeout' => 5,
            'cache_seconds' => 60,
        ]]);
    }

    private function client(): SaimsClient
    {
        return app(SaimsClient::class);
    }

    public function test_it_sends_the_bearer_token_and_asks_for_json(): void
    {
        Http::fake(['*' => Http::response(['data' => []])]);

        $this->client()->get('/customers', ['search' => 'abc', 'page' => 2]);

        Http::assertSent(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $request->method() === 'GET'
                && $request->hasHeader('Authorization', 'Bearer '.self::TOKEN)
                && $request->hasHeader('Accept', 'application/json')
                && str_starts_with($request->url(), 'http://saims.test/api/v1/customers?')
                && $query === ['search' => 'abc', 'page' => '2'];
        });
    }

    public function test_it_only_ever_reads(): void
    {
        Http::fake(['*' => Http::response(['data' => []])]);

        $this->client()->get('/products');

        Http::assertSent(fn (Request $request) => $request->method() === 'GET');
        $this->assertFalse(method_exists(SaimsClient::class, 'post'));
        $this->assertFalse(method_exists(SaimsClient::class, 'put'));
        $this->assertFalse(method_exists(SaimsClient::class, 'delete'));
    }

    public function test_an_identical_request_within_the_cache_time_makes_one_http_call(): void
    {
        Http::fake(['*' => Http::response(['data' => [['id' => 1]]])]);

        $first = $this->client()->get('/customers', ['search' => 'a', 'page' => 1]);
        $second = $this->client()->get('/customers', ['page' => 1, 'search' => 'a']); // same query, other order

        $this->assertSame($first, $second);
        Http::assertSentCount(1);

        $this->client()->get('/customers', ['search' => 'b', 'page' => 1]); // different query
        Http::assertSentCount(2);
    }

    public function test_caching_can_be_turned_off(): void
    {
        config(['services.saims.cache_seconds' => 0]);
        Http::fake(['*' => Http::response(['data' => []])]);

        $this->client()->get('/customers');
        $this->client()->get('/customers');

        Http::assertSentCount(2);
    }

    public function test_a_failed_call_is_not_cached(): void
    {
        Http::fake(['*' => Http::sequence()->push([], 500)->push(['data' => []])]);

        try {
            $this->client()->get('/customers');
            $this->fail('expected an exception');
        } catch (SaimsException) {
        }

        $this->assertSame(['data' => []], $this->client()->get('/customers'));
        Http::assertSentCount(2);
    }

    public function test_the_cache_does_not_cross_tokens(): void
    {
        Http::fake(['*' => Http::response(['data' => []])]);

        $this->client()->get('/customers');
        config(['services.saims.token' => 'another-token']);
        $this->client()->get('/customers');

        Http::assertSentCount(2);
    }

    public static function failures(): array
    {
        return [
            '401' => [401, SaimsFailure::Unauthorized],
            '403' => [403, SaimsFailure::Forbidden],
            '404' => [404, SaimsFailure::NotFound],
            '429' => [429, SaimsFailure::RateLimited],
            '500' => [500, SaimsFailure::Failed],
            '503' => [503, SaimsFailure::Failed],
        ];
    }

    #[DataProvider('failures')]
    public function test_http_errors_become_friendly_failures_and_are_not_retried(int $status, SaimsFailure $expected): void
    {
        Http::fake(['*' => Http::response(['message' => 'secret internal details'], $status)]);

        try {
            $this->client()->get('/customers');
            $this->fail('expected an exception');
        } catch (SaimsException $e) {
            $this->assertSame($expected, $e->failure);
            $this->assertSame($expected->message(), $e->getMessage());
            $this->assertStringNotContainsString('secret internal details', $e->getMessage());
        }

        Http::assertSentCount(1); // an answer from SAIMS is never retried
    }

    public function test_a_connection_error_is_retried_once_then_reported_as_unreachable(): void
    {
        $attempts = 0;
        Http::fake(['*' => function () use (&$attempts) {
            $attempts++;

            throw new ConnectionException('cURL error 7: connection refused to http://saims.test');
        }]);

        try {
            $this->client()->get('/customers');
            $this->fail('expected an exception');
        } catch (SaimsException $e) {
            $this->assertSame(SaimsFailure::Unreachable, $e->failure);
            $this->assertStringNotContainsString('saims.test', $e->getMessage());
        }

        $this->assertSame(2, $attempts, 'tried twice, then gave up');
    }

    public function test_a_body_without_data_is_a_failure(): void
    {
        Http::fake(['*' => Http::response(['oops' => true])]);

        $this->expectException(SaimsException::class);
        $this->client()->get('/customers');
    }

    public function test_a_disabled_or_unconfigured_saims_makes_no_request(): void
    {
        Http::fake();

        config(['services.saims.enabled' => false]);
        try {
            $this->client()->get('/customers');
        } catch (SaimsException $e) {
            $this->assertSame(SaimsFailure::Disabled, $e->failure);
        }

        config(['services.saims.enabled' => true, 'services.saims.token' => null]);
        try {
            $this->client()->get('/customers');
        } catch (SaimsException $e) {
            $this->assertSame(SaimsFailure::NotConfigured, $e->failure);
        }

        Http::assertNothingSent();
    }

    public function test_it_stays_under_the_rate_limit_without_calling_saims(): void
    {
        Http::fake(['*' => Http::response(['data' => []])]);

        foreach (range(1, 55) as $i) {
            $this->client()->get('/customers', ['page' => $i]); // different pages, so no cache hit
        }

        try {
            $this->client()->get('/customers', ['page' => 56]);
            $this->fail('expected an exception');
        } catch (SaimsException $e) {
            $this->assertSame(SaimsFailure::RateLimited, $e->failure);
        }

        Http::assertSentCount(55);
    }

    public function test_the_token_never_reaches_the_logs_or_an_exception(): void
    {
        $logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged) {
            $logged[] = $event->message.' '.json_encode($event->context);
        });

        // One fake, answering with a different status each time (a second Http::fake would not replace the first).
        Http::fake(['*' => Http::sequence()
            ->push(['token' => self::TOKEN], 401)
            ->push(['token' => self::TOKEN], 403)
            ->push(['token' => self::TOKEN], 404)
            ->push(['token' => self::TOKEN], 429)
            ->push(['token' => self::TOKEN], 500)]);

        foreach ([401, 403, 404, 429, 500] as $status) {
            try {
                $this->client()->get('/customers', ['page' => $status]);
            } catch (SaimsException $e) {
                $this->assertStringNotContainsString(self::TOKEN, $e->getMessage().json_encode($e->toArray()));
            }
        }

        $this->assertNotEmpty($logged, 'failures should be logged');
        $this->assertStringNotContainsString(self::TOKEN, implode("\n", $logged));
    }
}
