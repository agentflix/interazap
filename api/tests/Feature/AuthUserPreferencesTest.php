<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthUserPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_preferences_returns_full_defaults_for_new_user(): void
    {
        $user = AuthUser::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/profile/preferences')
            ->assertOk()
            ->assertJsonPath('data.appearance.theme', 'system')
            ->assertJsonPath('data.appearance.density', 'normal')
            ->assertJsonPath('data.appearance.fontSize', 'medium')
            ->assertJsonPath('data.behavior.sound', true)
            ->assertJsonPath('data.behavior.chatNotify', true)
            ->assertJsonPath('data.behavior.quickReply', false)
            ->assertJsonPath('data.behavior.confirmBulk', true)
            ->assertJsonPath('data.behavior.ticketOpenMode', 'modal')
            ->assertJsonPath('data.crmDefaults.negotiationType', 'basic')
            ->assertJsonPath('data.crmDefaults.taskStatus', 'pending')
            ->assertJsonPath('data.crmDefaults.pipelineView', 'kanban')
            ->assertJsonPath('data.crmDefaults.negotiationOrder', 'date')
            ->assertJsonPath('data.security.sessionTimeout', 60)
            ->assertJsonPath('data.accessibility.highContrast', false)
            ->assertJsonPath('data.accessibility.reducedMotion', false);
    }

    public function test_get_preferences_returns_merged_stored_values(): void
    {
        $user = AuthUser::factory()->create([
            'preferences' => [
                'appearance' => ['theme' => 'dark'],
            ],
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/profile/preferences')
            ->assertOk()
            ->assertJsonPath('data.appearance.theme', 'dark')
            ->assertJsonPath('data.appearance.density', 'normal')
            ->assertJsonPath('data.appearance.fontSize', 'medium');
    }

    public function test_patch_preferences_does_deep_merge(): void
    {
        $user = AuthUser::factory()->create([
            'preferences' => [
                'appearance' => ['theme' => 'dark', 'density' => 'compact'],
                'behavior' => ['sound' => false, 'quickReply' => true],
            ],
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/auth/profile/preferences', [
                'appearance' => ['theme' => 'light'],
            ])
            ->assertOk()
            ->assertJsonPath('data.appearance.theme', 'light')
            ->assertJsonPath('data.appearance.density', 'compact')
            ->assertJsonPath('data.appearance.fontSize', 'medium')
            ->assertJsonPath('data.behavior.sound', false)
            ->assertJsonPath('data.behavior.quickReply', true);
    }

    public function test_patch_preferences_with_invalid_theme_returns_422(): void
    {
        $user = AuthUser::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/auth/profile/preferences', [
                'appearance' => ['theme' => 'invalid'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['appearance.theme']);
    }

    public function test_patch_preferences_with_invalid_crm_task_status_returns_422(): void
    {
        $user = AuthUser::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/auth/profile/preferences', [
                'crmDefaults' => ['taskStatus' => 'invalid_status'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['crmDefaults.taskStatus']);
    }

    public function test_user_cannot_update_other_user_preferences(): void
    {
        $user = AuthUser::factory()->create();
        $other = AuthUser::factory()->create(['preferences' => ['appearance' => ['theme' => 'dark']]]);

        // Try to update other user's preferences by patching the URL (should be blocked by policy)
        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/auth/profile/preferences', [
                'appearance' => ['theme' => 'light'],
            ])
            ->assertOk(); // The policy uses auth()->user(), so it's always the authenticated user

        // Verify other user's preferences were NOT changed
        $other->refresh();
        $this->assertEquals('dark', $other->preferences['appearance']['theme']);
    }

    public function test_patch_preferences_with_valid_enum_values(): void
    {
        $user = AuthUser::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/auth/profile/preferences', [
                'crmDefaults' => ['taskStatus' => 'in_progress'],
                'behavior' => ['ticketOpenMode' => 'page'],
            ])
            ->assertOk()
            ->assertJsonPath('data.crmDefaults.taskStatus', 'in_progress')
            ->assertJsonPath('data.behavior.ticketOpenMode', 'page');
    }

    public function test_patch_preferences_with_null_session_timeout(): void
    {
        $user = AuthUser::factory()->create(['preferences' => [
            'security' => ['sessionTimeout' => 60],
        ]]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/auth/profile/preferences', [
                'security' => ['sessionTimeout' => null],
            ])
            ->assertOk()
            ->assertJsonPath('data.security.sessionTimeout', 60); // null was not saved as null, default stays
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/auth/profile/preferences')
            ->assertUnauthorized();
    }
}
