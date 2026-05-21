<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Ai\DTOs\AiMediaTranscriptionDTO;
use Domain\Ai\Enums\AiMediaTranscriptionStatus;
use Domain\Ai\Jobs\AiMediaTranscriptionJob;
use Domain\Ai\Models\AiUsageLog;
use Domain\Ai\Services\AiMediaTranscriptionService;
use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatMessage;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MediaTranscriptionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private AuthUser $user;

    private PlatformTenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var AuthUser $user */
        $user = AuthUser::factory()->create();
        $this->user = $user;

        $this->tenant = PlatformTenant::query()->find($user->tenant_id);

        $permission = AuthPermission::query()->firstOrCreate(
            ['name' => 'ai.autopilots.manage', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );
        $this->user->givePermissionTo($permission);
        $this->actingAs($this->user, 'sanctum');
    }

    // ─── Enum Tests ───

    public function test_transcription_status_enum_has_expected_cases(): void
    {
        $this->assertCount(4, AiMediaTranscriptionStatus::cases());
        $this->assertSame('pending', AiMediaTranscriptionStatus::PENDING->value);
        $this->assertSame('processing', AiMediaTranscriptionStatus::PROCESSING->value);
        $this->assertSame('completed', AiMediaTranscriptionStatus::COMPLETED->value);
        $this->assertSame('failed', AiMediaTranscriptionStatus::FAILED->value);
    }

    public function test_transcription_status_is_finished_returns_correctly(): void
    {
        $this->assertTrue(AiMediaTranscriptionStatus::COMPLETED->isFinished());
        $this->assertTrue(AiMediaTranscriptionStatus::FAILED->isFinished());
        $this->assertFalse(AiMediaTranscriptionStatus::PENDING->isFinished());
        $this->assertFalse(AiMediaTranscriptionStatus::PROCESSING->isFinished());
    }

    public function test_transcription_status_can_retry(): void
    {
        $this->assertTrue(AiMediaTranscriptionStatus::FAILED->canRetry());
        $this->assertFalse(AiMediaTranscriptionStatus::COMPLETED->canRetry());
        $this->assertFalse(AiMediaTranscriptionStatus::PROCESSING->canRetry());
    }

    // ─── DTO Tests ───

    public function test_dto_creates_from_array_and_converts_back(): void
    {
        $data = [
            'message_id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) Str::orderedUuid(),
            'media_type' => 'audio',
            'file_path' => '/tmp/test.mp3',
            'transcription' => 'Hello world',
            'provider' => 'openai',
            'model_name' => 'whisper-1',
            'input_tokens' => 100,
            'output_tokens' => 50,
            'cost' => 0.006,
            'latency_ms' => 1500,
            'duration_seconds' => 60,
            'file_size_kb' => 512,
        ];

        $dto = AiMediaTranscriptionDTO::fromArray($data);

        $this->assertSame('audio', $dto->mediaType);
        $this->assertSame('Hello world', $dto->transcription);
        $this->assertSame(100, $dto->inputTokens);
        $this->assertSame(0.006, $dto->cost);

        $array = $dto->toArray();
        $this->assertSame($data['message_id'], $array['message_id']);
        $this->assertSame('whisper-1', $array['model_name']);
    }

    // ─── Service Tests ───

    public function test_should_transcribe_returns_false_when_disabled(): void
    {
        $this->tenant->update([
            'media_transcription_audio_enabled' => false,
            'media_transcription_image_enabled' => false,
            'media_transcription_video_enabled' => false,
        ]);

        $service = app(AiMediaTranscriptionService::class);

        $this->assertFalse($service->shouldTranscribe($this->tenant, 'audio'));
        $this->assertFalse($service->shouldTranscribe($this->tenant, 'image'));
        $this->assertFalse($service->shouldTranscribe($this->tenant, 'video'));
    }

    public function test_should_transcribe_returns_true_when_enabled(): void
    {
        $this->tenant->update([
            'media_transcription_audio_enabled' => true,
            'media_transcription_image_enabled' => true,
            'media_transcription_video_enabled' => true,
        ]);

        $service = app(AiMediaTranscriptionService::class);

        $this->assertTrue($service->shouldTranscribe($this->tenant, 'audio'));
        $this->assertTrue($service->shouldTranscribe($this->tenant, 'image'));
        $this->assertTrue($service->shouldTranscribe($this->tenant, 'video'));
    }

    public function test_resolve_media_type_maps_mime_types_correctly(): void
    {
        $service = app(AiMediaTranscriptionService::class);

        $this->assertSame('audio', $service->resolveMediaType('audio/mpeg'));
        $this->assertSame('audio', $service->resolveMediaType('audio/ogg'));
        $this->assertSame('image', $service->resolveMediaType('image/jpeg'));
        $this->assertSame('image', $service->resolveMediaType('image/png'));
        $this->assertSame('video', $service->resolveMediaType('video/mp4'));
        $this->assertNull($service->resolveMediaType('application/pdf'));
        $this->assertNull($service->resolveMediaType('text/plain'));
    }

    public function test_estimate_cost_returns_expected_values(): void
    {
        $service = app(AiMediaTranscriptionService::class);

        $audioCost = $service->estimateCost('audio', 120); // 2 minutes
        $this->assertGreaterThan(0, $audioCost);

        $imageCost = $service->estimateCost('image', 0);
        $this->assertGreaterThan(0, $imageCost);

        $videoCost = $service->estimateCost('video', 30);
        $this->assertGreaterThan(0, $videoCost);
    }

    // ─── Configuration API Tests ───

    public function test_can_get_transcription_settings(): void
    {
        $this->tenant->update([
            'media_transcription_audio_enabled' => true,
            'media_transcription_image_enabled' => false,
            'media_transcription_video_enabled' => true,
            'media_transcription_audio_max_minutes' => 10,
            'media_transcription_image_max_per_message' => 5,
            'media_transcription_video_max_seconds' => 60,
        ]);

        $this->getJson('/api/media-transcription')
            ->assertOk()
            ->assertJsonPath('data.media_transcription_audio_enabled', true)
            ->assertJsonPath('data.media_transcription_image_enabled', false)
            ->assertJsonPath('data.media_transcription_video_enabled', true)
            ->assertJsonPath('data.media_transcription_audio_max_minutes', 10)
            ->assertJsonPath('data.media_transcription_image_max_per_message', 5)
            ->assertJsonPath('data.media_transcription_video_max_seconds', 60);
    }

    public function test_can_update_transcription_settings(): void
    {
        $this->putJson('/api/media-transcription', [
            'media_transcription_audio_enabled' => true,
            'media_transcription_image_enabled' => true,
            'media_transcription_video_enabled' => false,
            'media_transcription_audio_max_minutes' => 15,
            'media_transcription_image_max_per_message' => 7,
            'media_transcription_video_max_seconds' => 90,
        ])
            ->assertOk()
            ->assertJsonPath('data.media_transcription_audio_enabled', true)
            ->assertJsonPath('data.media_transcription_image_enabled', true)
            ->assertJsonPath('data.media_transcription_video_enabled', false)
            ->assertJsonPath('data.media_transcription_audio_max_minutes', 15);

        $this->assertDatabaseHas('platform_tenants', [
            'id' => $this->tenant->id,
            'media_transcription_audio_max_minutes' => 15,
        ]);
    }

    public function test_update_validates_limits(): void
    {
        $this->putJson('/api/media-transcription', [
            'media_transcription_audio_enabled' => true,
            'media_transcription_image_enabled' => true,
            'media_transcription_video_enabled' => true,
            'media_transcription_audio_max_minutes' => 0, // min is 1
            'media_transcription_image_max_per_message' => 11, // max is 10
            'media_transcription_video_max_seconds' => 3, // min is 5
        ])->assertUnprocessable();
    }

    // ─── ChatMessage Model Tests ───

    public function test_chat_message_has_transcription_fields(): void
    {
        $message = ChatMessage::factory()->create([
            'tenant_id' => $this->tenant->id,
            'media_transcription' => 'Test transcription',
            'media_transcription_provider' => 'openai',
            'media_transcription_status' => AiMediaTranscriptionStatus::COMPLETED->value,
            'media_transcription_tokens' => 150,
            'media_transcription_cost' => 0.005,
        ]);

        $extended = $message->extended()->first();
        $this->assertNotNull($extended);
        $this->assertSame('Test transcription', $extended->media_transcription);
        $this->assertSame('openai', $extended->media_transcription_provider);
        $this->assertSame(AiMediaTranscriptionStatus::COMPLETED->value, $extended->media_transcription_status);
        $this->assertSame(150, $extended->media_transcription_tokens);
        $this->assertEqualsWithDelta(0.005, (float) $extended->media_transcription_cost, 0.0001);
    }

    // ─── Job Tests ───

    public function test_transcription_job_skips_already_completed_message(): void
    {
        $message = ChatMessage::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'audio',
            'media_transcription_status' => AiMediaTranscriptionStatus::COMPLETED->value,
            'media_transcription' => 'Already transcribed',
        ]);

        $job = new AiMediaTranscriptionJob(
            (string) $message->id,
            (string) $this->tenant->id,
            'audio',
            '/tmp/test.mp3',
        );

        // Should return early without error
        $this->assertNull($job->handle(
            app(AiMediaTranscriptionService::class),
            app(\Domain\Chat\Services\ChatBroadcastService::class),
        ));
    }

    // ─── Transcription Report Tests ───

    public function test_transcription_report_endpoint_returns_correct_structure(): void
    {
        // Create transcription usage logs
        AiUsageLog::factory()
            ->count(3)
            ->forFeature('media_transcription_audio')
            ->create([
                'tenant_id' => $this->tenant->id,
                'model_name' => 'whisper-1',
                'provider' => 'openai',
                'metadata' => ['media_type' => 'audio'],
            ]);

        AiUsageLog::factory()
            ->count(2)
            ->forFeature('media_transcription_image')
            ->create([
                'tenant_id' => $this->tenant->id,
                'model_name' => 'gpt-4o',
                'provider' => 'openai',
                'metadata' => ['media_type' => 'image'],
            ]);

        $response = $this->getJson('/api/ai/usage/transcription?start_date='.now()->startOfMonth()->format('Y-m-d').'&end_date='.now()->format('Y-m-d'));

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'summary' => ['total_transcriptions', 'total_tokens', 'total_cost', 'avg_latency_ms'],
                    'by_type',
                    'by_model',
                    'daily_cost',
                ],
            ]);

        $data = $response->json('data');
        $this->assertSame(5, $data['summary']['total_transcriptions']);
    }

    public function test_transcription_report_is_tenant_isolated(): void
    {
        // Create logs for this tenant
        AiUsageLog::factory()
            ->count(3)
            ->forFeature('media_transcription_audio')
            ->create([
                'tenant_id' => $this->tenant->id,
                'metadata' => ['media_type' => 'audio'],
            ]);

        // Create logs for another tenant
        $otherTenant = PlatformTenant::factory()->create();
        AiUsageLog::factory()
            ->count(5)
            ->forFeature('media_transcription_audio')
            ->create([
                'tenant_id' => $otherTenant->id,
                'metadata' => ['media_type' => 'audio'],
            ]);

        $response = $this->getJson('/api/ai/usage/transcription?start_date='.now()->startOfMonth()->format('Y-m-d').'&end_date='.now()->format('Y-m-d'));

        $data = $response->json('data');
        // Should only see this tenant's 3 logs, not the other tenant's 5
        $this->assertSame(3, $data['summary']['total_transcriptions']);
    }

    // ─── Resource Includes Transcription Fields ───

    public function test_chat_message_resource_includes_transcription_fields(): void
    {
        $message = ChatMessage::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'audio',
            'media_transcription' => 'Transcrição de teste',
            'media_transcription_status' => AiMediaTranscriptionStatus::COMPLETED->value,
            'media_transcription_provider' => 'openai',
        ]);

        $resource = new \Domain\Chat\Http\Resources\ChatMessageResource($message);
        $array = $resource->resolve(request());

        $this->assertArrayHasKey('media_transcription', $array);
        $this->assertArrayHasKey('media_transcription_status', $array);
        $this->assertArrayHasKey('media_transcription_provider', $array);
        $this->assertSame('Transcrição de teste', $array['media_transcription']);
        $this->assertSame('completed', $array['media_transcription_status']);
    }
}
