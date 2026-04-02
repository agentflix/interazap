<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use Domain\Shared\Infrastructure\Gateway\GatewayHttpClient;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class GatewayHttpClientTest extends TestCase
{
    public function test_constructor_throws_when_gateway_url_is_missing(): void
    {
        config()->set('services.gateway', []);
        config()->set('services.uazapi.base_url', '');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('services.gateway.url is not configured');

        new GatewayHttpClient;
    }

    public function test_post_get_and_delete_use_normalized_endpoint_and_return_json(): void
    {
        config()->set('services.gateway', [
            'url' => 'http://gateway.test/',
            'timeout' => 5,
            'retry_attempts' => 2,
            'retry_delay_ms' => 100,
            'api_key' => 'test-key',
        ]);
        config()->set('services.uazapi.base_url', '');

        Http::fake([
            'http://gateway.test/*' => Http::response(['ok' => true], 200),
        ]);

        $client = new GatewayHttpClient;

        $post = $client->post('internal/messages', ['foo' => 'bar'], ['x-custom' => '1']);
        $get = $client->get('/internal/messages', ['page' => 1]);
        $delete = $client->delete('internal/messages/1');

        $this->assertSame(['ok' => true], $post);
        $this->assertSame(['ok' => true], $get);
        $this->assertSame(['ok' => true], $delete);

        Http::assertSentCount(3);
        Http::assertSent(fn ($request): bool => str_starts_with((string) $request->url(), 'http://gateway.test/')
            && $request->hasHeader('x-api-key', 'test-key'));
    }

    public function test_uses_uazapi_base_url_override_when_present(): void
    {
        config()->set('services.gateway', [
            'url' => 'http://gateway.test',
        ]);
        config()->set('services.uazapi.base_url', 'http://override.test/');

        Http::fake([
            'http://override.test/*' => Http::response(['overridden' => true], 200),
        ]);

        $client = new GatewayHttpClient;
        $result = $client->get('health');

        $this->assertSame(['overridden' => true], $result);
        Http::assertSent(fn ($request): bool => str_starts_with((string) $request->url(), 'http://override.test/'));
    }
}
