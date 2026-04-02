<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use Domain\Auth\Actions\AuthProfileActions;
use Domain\Auth\DTOs\AuthProfileDTO;
use Domain\Auth\DTOs\AuthUpdatePasswordDTO;
use Domain\Auth\Models\AuthUser;
use Domain\Auth\Services\AuthAvatarManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AuthProfileActionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_get_returns_profile_payload(): void
    {
        $user = AuthUser::factory()->create();
        $actions = new AuthProfileActions(new AuthAvatarManager);

        $payload = $actions->get($user);

        $this->assertSame($user->id, $payload['id']);
        $this->assertSame($user->email, $payload['email']);
    }

    public function test_update_updates_profile_fields(): void
    {
        $user = AuthUser::factory()->create();
        $actions = new AuthProfileActions(new AuthAvatarManager);

        $dto = AuthProfileDTO::fromArray([
            'name' => 'Novo Nome',
            'email' => $user->email,
        ]);

        $payload = $actions->update($user, $dto);

        $this->assertSame('Novo Nome', $payload['name']);
        $this->assertSame('Novo Nome', $user->fresh()->name);
    }

    public function test_update_password_requires_current_password(): void
    {
        $user = AuthUser::factory()->create([
            'password' => Hash::make('secret'),
        ]);
        $actions = new AuthProfileActions(new AuthAvatarManager);

        $this->expectException(ValidationException::class);

        $actions->updatePassword($user, AuthUpdatePasswordDTO::fromArray([
            'current_password' => 'wrong',
            'password' => 'newpass',
            'password_confirmation' => 'newpass',
        ]));
    }

    public function test_update_password_updates_hash(): void
    {
        $user = AuthUser::factory()->create([
            'password' => Hash::make('secret'),
        ]);
        $actions = new AuthProfileActions(new AuthAvatarManager);

        $actions->updatePassword($user, AuthUpdatePasswordDTO::fromArray([
            'current_password' => 'secret',
            'password' => 'newpass',
            'password_confirmation' => 'newpass',
        ]));

        $this->assertTrue(Hash::check('newpass', $user->fresh()->password));
    }

    public function test_update_and_delete_avatar(): void
    {
        Storage::fake('public');

        $user = AuthUser::factory()->create();
        $actions = new AuthProfileActions(new AuthAvatarManager);

        $file = UploadedFile::fake()->image('avatar.jpg');
        $result = $actions->updateAvatar($user, $file);

        $this->assertNotEmpty($result['avatar_url']);
        $this->assertNotNull($user->fresh()->avatar_url);

        $removed = $actions->deleteAvatar($user);

        $this->assertNull($removed['avatar_url']);
        $this->assertNull($user->fresh()->avatar_url);
    }
}
