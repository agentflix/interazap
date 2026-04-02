<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthUser;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChatMediaControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_uploads_chat_media(): void
    {
        Storage::fake('public');
        $user = AuthUser::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/chat/media', [
                'file' => UploadedFile::fake()->image('photo.jpg'),
            ])
            ->assertOk()
            ->assertJsonStructure(['data' => ['url', 'file_name', 'mime_type', 'size', 'metadata']]);
    }
}
