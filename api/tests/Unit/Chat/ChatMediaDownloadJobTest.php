<?php

declare(strict_types=1);

namespace Tests\Unit\Chat;

use Domain\Chat\Jobs\ChatMediaDownloadJob;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Services\ChatBroadcastService;
use Domain\Chat\Services\ChatGatewayService;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Services\GatewayBroadcastService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class ChatMediaDownloadJobTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Date::setTestNow();
        Mockery::close();
        parent::tearDown();
    }

    public function test_returns_when_message_not_found(): void
    {
        Http::fake();
        $gateway = Mockery::mock(ChatGatewayService::class);
        $broadcast = new ChatBroadcastService(new GatewayBroadcastService);

        $gateway->shouldNotReceive('downloadMedia');

        $job = new ChatMediaDownloadJob(
            Str::uuid()->toString(),
            Str::uuid()->toString(),
            'token',
            'ext-0'
        );
        $job->handle($gateway, $broadcast);

        $this->assertTrue(true);
    }

    public function test_skips_when_media_already_local(): void
    {
        config()->set('app.url', 'http://app.test');

        $tenant = PlatformTenant::factory()->create();
        $ticket = ChatTicket::factory()->create(['tenant_id' => $tenant->id]);
        $message = ChatMessage::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'file_url' => 'http://app.test/storage/chat/media/file.png',
        ]);

        $gateway = Mockery::mock(ChatGatewayService::class);
        Http::fake();
        $broadcast = new ChatBroadcastService(new GatewayBroadcastService);

        $gateway->shouldNotReceive('downloadMedia');

        $job = new ChatMediaDownloadJob((string) $message->id, (string) $tenant->id, 'token', 'ext-local');
        $job->handle($gateway, $broadcast);

        $this->assertSame('http://app.test/storage/chat/media/file.png', $message->fresh()->file_url);
    }

    public function test_downloads_media_and_updates_message(): void
    {
        Storage::fake('public');

        $tenant = PlatformTenant::factory()->create();
        $ticket = ChatTicket::factory()->create(['tenant_id' => $tenant->id]);
        $message = ChatMessage::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'file_url' => null,
            'mime_type' => null,
        ]);

        Http::fake([
            'http://files.test/*' => Http::response('file-content', 200),
        ]);

        $gateway = Mockery::mock(ChatGatewayService::class);
        $gateway->shouldReceive('downloadMedia')
            ->once()
            ->with('token-1', 'ext-1')
            ->andReturn([
                'fileURL' => 'http://files.test/file.jpg',
                'mimetype' => 'image/jpeg',
            ]);

        $broadcast = new ChatBroadcastService(new GatewayBroadcastService);

        $job = new ChatMediaDownloadJob(
            (string) $message->id,
            (string) $tenant->id,
            'token-1',
            'ext-1',
            null,
            'image/jpeg'
        );

        $job->handle($gateway, $broadcast);

        $message->refresh();

        $this->assertNotNull($message->file_url);
        $this->assertSame('image/jpeg', $message->mime_type);
        $this->assertNotNull($message->file_name);
        $this->assertSame(strlen('file-content'), $message->file_size);
        $this->assertNotEmpty(Storage::disk('public')->allFiles());
    }

    public function test_downloads_media_from_base64(): void
    {
        Storage::fake('public');

        $tenant = PlatformTenant::factory()->create();
        $ticket = ChatTicket::factory()->create(['tenant_id' => $tenant->id]);
        $message = ChatMessage::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'file_url' => null,
            'mime_type' => null,
        ]);

        Http::fake();
        $gateway = Mockery::mock(ChatGatewayService::class);
        $gateway->shouldReceive('downloadMedia')
            ->once()
            ->with('token-2', 'ext-2')
            ->andReturn([
                'base64Data' => base64_encode('base64-content'),
                'mimetype' => 'application/pdf',
            ]);

        $broadcast = new ChatBroadcastService(new GatewayBroadcastService);

        $job = new ChatMediaDownloadJob(
            (string) $message->id,
            (string) $tenant->id,
            'token-2',
            'ext-2',
            null,
            'application/pdf'
        );

        $job->handle($gateway, $broadcast);

        $message->refresh();

        $this->assertSame('application/pdf', $message->mime_type);
        $this->assertNotNull($message->file_url);
    }

    public function test_rejects_invalid_mime_type(): void
    {
        Storage::fake('public');

        $tenant = PlatformTenant::factory()->create();
        $ticket = ChatTicket::factory()->create(['tenant_id' => $tenant->id]);
        $message = ChatMessage::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'file_url' => null,
        ]);

        Http::fake();
        $gateway = Mockery::mock(ChatGatewayService::class);
        $gateway->shouldReceive('downloadMedia')
            ->once()
            ->andReturn([
                'base64Data' => base64_encode('file-content'),
                'mimetype' => 'application/x-msdownload',
            ]);

        $broadcast = new ChatBroadcastService(new GatewayBroadcastService);

        $job = new ChatMediaDownloadJob((string) $message->id, (string) $tenant->id, 'token', 'ext-invalid');
        $job->handle($gateway, $broadcast);

        $this->assertNull($message->fresh()->file_url);
    }

    public function test_handles_http_failure_gracefully(): void
    {
        Storage::fake('public');

        $tenant = PlatformTenant::factory()->create();
        $ticket = ChatTicket::factory()->create(['tenant_id' => $tenant->id]);
        $message = ChatMessage::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'file_url' => null,
        ]);

        Http::fake([
            'http://files.test/*' => Http::response('fail', 500),
        ]);

        $gateway = Mockery::mock(ChatGatewayService::class);
        $gateway->shouldReceive('downloadMedia')
            ->once()
            ->andReturn([
                'fileURL' => 'http://files.test/file.png',
                'mimetype' => 'image/png',
            ]);

        $broadcast = new ChatBroadcastService(new GatewayBroadcastService);

        $job = new ChatMediaDownloadJob((string) $message->id, (string) $tenant->id, 'token', 'ext-fail');
        $job->handle($gateway, $broadcast);

        $this->assertNull($message->fresh()->file_url);
    }

    public function test_downloads_media_and_updates_path(): void
    {
        config()->set('app.url', 'http://app.test');
        Storage::fake('public');
        Date::setTestNow(Date::create(2026, 1, 20, 10, 0, 0));

        $tenant = PlatformTenant::factory()->create();
        $ticket = ChatTicket::factory()->create(['tenant_id' => $tenant->id]);
        $message = ChatMessage::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'file_url' => null,
            'mime_type' => null,
        ]);

        Http::fake();
        $gateway = Mockery::mock(ChatGatewayService::class);
        $gateway->shouldReceive('downloadMedia')
            ->once()
            ->andReturn([
                'base64Data' => base64_encode('file-content'),
                'mimetype' => 'image/png',
            ]);

        $broadcast = new ChatBroadcastService(new GatewayBroadcastService);

        $job = new ChatMediaDownloadJob((string) $message->id, (string) $tenant->id, 'token', 'ext-10');
        $job->handle($gateway, $broadcast);

        Storage::disk('public')->assertExists("chat/media/{$tenant->id}/2026/01/20/ext-10.png");
    }
}
