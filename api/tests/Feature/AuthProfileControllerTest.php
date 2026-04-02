<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthUser;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuthProfileControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_profile_endpoints_update_profile_and_password(): void
    {
        $user = AuthUser::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/profile')
            ->assertOk()
            ->assertJsonFragment(['email' => $user->email]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/auth/profile', [
                'name' => 'Novo Nome',
                'phone' => '5511999999999',
            ])
            ->assertOk()
            ->assertJsonFragment(['name' => 'Novo Nome']);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/auth/profile/password', [
                'current_password' => 'password',
                'password' => 'newpassword',
                'password_confirmation' => 'newpassword',
            ])
            ->assertOk();
    }

    public function test_profile_avatar_upload_and_delete(): void
    {
        Storage::fake('public');

        $user = AuthUser::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/profile/avatar', [
                'image' => UploadedFile::fake()->image('avatar.jpg'),
            ])
            ->assertOk()
            ->assertJsonStructure(['data' => ['avatar_url']]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/auth/profile/avatar')
            ->assertOk();
    }
}
