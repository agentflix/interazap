<?php

declare(strict_types=1);

namespace Tests\Unit\Chat;

use Domain\Shared\Infrastructure\WhatsApp\Concerns\HasRetryPolicy;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Tests\TestCase;

class HasRetryPolicyTraitTest extends TestCase
{
    public function test_should_retry_for_connection_exception(): void
    {
        $harness = new HasRetryPolicyHarness;

        $this->assertTrue($harness->shouldRetryPublic(new ConnectionException('timeout')));
    }

    public function test_should_retry_for_server_error_and_too_many_requests(): void
    {
        $harness = new HasRetryPolicyHarness;

        $serverError = new RequestException(new Response(new PsrResponse(500)));
        $rateLimited = new RequestException(new Response(new PsrResponse(429)));

        $this->assertTrue($harness->shouldRetryPublic($serverError));
        $this->assertTrue($harness->shouldRetryPublic($rateLimited));
    }

    public function test_should_not_retry_for_client_error_and_generic_exception(): void
    {
        $harness = new HasRetryPolicyHarness;

        $badRequest = new RequestException(new Response(new PsrResponse(400)));

        $this->assertFalse($harness->shouldRetryPublic($badRequest));
        $this->assertFalse($harness->shouldRetryPublic(new \RuntimeException('no-retry')));
    }

    public function test_uses_configured_retry_delay_when_available(): void
    {
        config()->set('services.http_retry_delay_ms', 250);

        $harness = new HasRetryPolicyHarness;

        $this->assertSame(250, $harness->retryDelayMsPublic());
        $this->assertNotNull($harness->httpClientPublic());
    }
}

final class HasRetryPolicyHarness
{
    use HasRetryPolicy;

    public function shouldRetryPublic(\Exception $exception): bool
    {
        return $this->shouldRetry($exception);
    }

    public function retryDelayMsPublic(): int
    {
        return $this->getRetryDelayMs();
    }

    public function httpClientPublic(): \Illuminate\Http\Client\PendingRequest
    {
        return $this->createHttpClient();
    }
}
