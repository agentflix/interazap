<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use Domain\Auth\Models\AuthUser;
use Domain\Auth\Services\AuthAvatarManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuthAvatarManagerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_update_stores_avatar_and_removes_previous_one(): void
    {
        Storage::fake('public');

        $user = AuthUser::factory()->create([
            'avatar_url' => 'http://localhost/storage/profiles/old/avatar.jpg',
        ]);

        Storage::disk('public')->put('profiles/old/avatar.jpg', 'old-file');

        $manager = new AuthAvatarManager;
        $file = UploadedFile::fake()->image('avatar.png');
        $result = $manager->update($user, $file);

        $this->assertNotEmpty($result['avatar_url']);
        Storage::disk('public')->assertMissing('profiles/old/avatar.jpg');
    }

    public function test_remove_clears_avatar_even_with_external_url(): void
    {
        Storage::fake('public');

        $user = AuthUser::factory()->create([
            'avatar_url' => 'https://cdn.example.com/avatar.png',
        ]);

        $manager = new AuthAvatarManager;
        $result = $manager->remove($user);

        $this->assertNull($result['avatar_url']);
        $this->assertNull($user->fresh()->avatar_url);
    }
}
