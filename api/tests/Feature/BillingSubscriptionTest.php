<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BillingSubscriptionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_subscription_returns_current_plan_and_usage(): void
    {
        [$tenant, $admin] = $this->createTenantAdmin();

        $plan = PlatformPlan::factory()->create([
            'name' => 'Pro',
            'slug' => 'pro-'.Str::lower(Str::random(6)),
            'price_monthly' => 199,
            'limit_users' => 5,
            'chat_channels_limit' => 2,
        ]);

        $tenant->plan_id = $plan->id;
        $tenant->save();

        Sanctum::actingAs($admin, abilities: ['*']);

        $response = $this->getJson('/api/billing/subscription');

        $response->assertOk();
        $response->assertJsonPath('data.current_plan.id', $plan->id);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'current_plan',
                'usage' => ['users', 'instances', 'storage', 'negotiations', 'ai'],
                'next_invoice',
            ],
        ]);
    }

    public function test_non_admin_user_cannot_access_subscription_endpoints(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $user = AuthUser::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        AuthPermission::query()->firstOrCreate(
            ['name' => 'billing.view', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );

        $user->givePermissionTo('billing.view');

        Sanctum::actingAs($user, abilities: ['*']);

        $this->getJson('/api/billing/subscription')->assertForbidden();
        $this->getJson('/api/billing/plans')->assertForbidden();
    }

    public function test_subscription_defaults_ai_disabled_when_current_plan_is_unavailable(): void
    {
        [$tenant, $admin] = $this->createTenantAdmin();

        $plan = PlatformPlan::query()->findOrFail($tenant->plan_id);
        $plan->delete();

        Sanctum::actingAs($admin, abilities: ['*']);

        $response = $this->getJson('/api/billing/subscription');

        $response->assertOk();
        $response->assertJsonPath('data.current_plan', null);
        $response->assertJsonPath('data.usage.ai.enabled', false);
    }

    public function test_plans_endpoint_returns_features_with_chatbot_included_and_ai_conditional(): void
    {
        [$tenant, $admin] = $this->createTenantAdmin();

        // Create a plan without AI (like Starter)
        $starterSlug = 'starter-test-'.Str::lower(Str::random(6));
        PlatformPlan::factory()->create([
            'name' => 'Starter',
            'slug' => $starterSlug,
            'price_monthly' => 97,
            'limit_users' => 5,
            'chat_channels_limit' => 1,
            'ai_enabled' => false,
        ]);

        // Create a plan with AI (like Professional)
        $proSlug = 'pro-test-'.Str::lower(Str::random(6));
        PlatformPlan::factory()->create([
            'name' => 'Professional',
            'slug' => $proSlug,
            'price_monthly' => 297,
            'limit_users' => 20,
            'chat_channels_limit' => 5,
            'ai_enabled' => true,
        ]);

        Sanctum::actingAs($admin, abilities: ['*']);

        $response = $this->getJson('/api/billing/plans');

        $response->assertOk();

        $plans = $response->json('data');

        // Find starter plan by slug
        $starter = collect($plans)->firstWhere('slug', $starterSlug);
        expect($starter)->not->toBeNull();
        expect($starter['features'])->toBeArray();

        // Starter: Chatbot should be included, AI should NOT be included
        $chatbotFeature = collect($starter['features'])->firstWhere('label', 'Chatbot (respostas automáticas)');
        expect($chatbotFeature)->not->toBeNull();
        expect($chatbotFeature['included'])->toBeTrue();

        $aiFeature = collect($starter['features'])->firstWhere('label', 'Inteligência Artificial');
        expect($aiFeature)->not->toBeNull();
        expect($aiFeature['included'])->toBeFalse();

        // Find professional plan by slug
        $pro = collect($plans)->firstWhere('slug', $proSlug);
        expect($pro)->not->toBeNull();

        // Professional: Both Chatbot and AI should be included
        $proChatbotFeature = collect($pro['features'])->firstWhere('label', 'Chatbot (respostas automáticas)');
        expect($proChatbotFeature)->not->toBeNull();
        expect($proChatbotFeature['included'])->toBeTrue();

        $proAiFeature = collect($pro['features'])->firstWhere('label', 'Inteligência Artificial');
        expect($proAiFeature)->not->toBeNull();
        expect($proAiFeature['included'])->toBeTrue();
    }

    private function createTenantAdmin(): array
    {
        $tenant = PlatformTenant::factory()->create();

        $admin = AuthUser::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => $tenant->primary_email,
        ]);

        $role = AuthRole::query()->firstOrCreate(
            ['id' => AuthRole::INQUILINO_ID],
            ['name' => AuthRole::INQUILINO_NAME, 'guard_name' => 'sanctum']
        );

        foreach (['billing.view', 'billing.plan.manage'] as $permission) {
            AuthPermission::query()->firstOrCreate(
                ['name' => $permission, 'guard_name' => 'sanctum'],
                ['id' => (string) Str::orderedUuid()]
            );
        }

        $admin->assignRole($role);
        $admin->givePermissionTo(['billing.view', 'billing.plan.manage']);

        return [$tenant->refresh(), $admin->refresh()];
    }
}
