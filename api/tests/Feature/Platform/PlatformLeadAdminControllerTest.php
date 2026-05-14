<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use Domain\Ai\Models\AiPromptSegment;
use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMContact;
use Domain\Platform\Models\PlatformLead;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Scopes\TenantScope;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformLeadAdminControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeAdmin(): AuthUser
    {
        $admin = AuthUser::factory()->create();
        AuthRole::query()->firstOrCreate(
            ['id' => AuthRole::INQUILINO_ID],
            ['name' => AuthRole::INQUILINO_NAME, 'guard_name' => 'sanctum']
        );
        $admin->assignRole(AuthRole::INQUILINO_ID);

        return $admin->refresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createLead(array $overrides = []): PlatformLead
    {
        return PlatformLead::query()->create(array_merge([
            'name' => 'Lead '.substr((string) str()->uuid(), 0, 8),
            'phone' => '11999999999',
            'email' => 'lead-'.str()->lower((string) str()->random(8)).'@example.com',
            'company' => 'Empresa Teste',
            'lgpd_consent' => true,
            'utm_source' => 'google',
            'utm_campaign' => 'campanha-x',
        ], $overrides));
    }

    private function ensureGeneralSegment(): void
    {
        AiPromptSegment::query()->firstOrCreate(
            ['code' => AiPromptSegment::CODE_GENERAL],
            [
                'name' => 'Segmento Geral',
                'content' => 'Prompt base geral para bootstrap',
                'is_active' => true,
            ]
        );
    }

    public function test_admin_can_list_platform_leads(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin, abilities: ['*']);

        $this->createLead(['name' => 'Alice Lead']);
        $this->createLead(['name' => 'Bruno Lead']);

        $this->getJson('/api/platform/leads')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_non_admin_cannot_list_platform_leads(): void
    {
        $user = AuthUser::factory()->create();
        Sanctum::actingAs($user, abilities: ['*']);

        $this->createLead();

        $this->getJson('/api/platform/leads')
            ->assertStatus(403);
    }

    public function test_admin_can_filter_by_search(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin, abilities: ['*']);

        $this->createLead([
            'name' => 'João Qualificado',
            'email' => 'joao-qualified@example.com',
        ]);

        $this->createLead([
            'name' => 'Maria Novo',
            'email' => 'maria-new@example.com',
        ]);

        $response = $this->getJson('/api/platform/leads?search=João');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'joao-qualified@example.com');
    }

    public function test_admin_can_convert_platform_lead(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin, abilities: ['*']);
        $this->ensureGeneralSegment();

        $lead = $this->createLead([
            'name' => 'Lead Conversão',
            'email' => 'lead-convert@example.com',
            'phone' => '11988887777',
        ]);

        $plan = PlatformPlan::factory()->create();

        $this->postJson("/api/platform/leads/{$lead->id}/convert", [
            'name' => 'Tenant Lead Conversão',
            'email' => 'owner-convert@example.com',
            'phone' => '11988887777',
            'document' => '12345678000199',
            'plan_id' => $plan->id,
        ])->assertOk();

        $tenant = PlatformTenant::query()->where('primary_email', 'owner-convert@example.com')->first();
        $this->assertNotNull($tenant);
        $this->assertSame('Tenant Lead Conversão', $tenant->name);

        $owner = AuthUser::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->where('email', 'owner-convert@example.com')
            ->first();
        $this->assertNotNull($owner);

        $contact = CRMContact::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->where('email', 'owner-convert@example.com')
            ->first();
        $this->assertNotNull($contact);
    }

    public function test_convert_rolls_back_when_user_creation_fails(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin, abilities: ['*']);

        $lead = $this->createLead([
            'email' => 'lead-rollback@example.com',
        ]);

        AuthUser::factory()->create([
            'email' => 'owner-rollback@example.com',
        ]);

        $this->postJson("/api/platform/leads/{$lead->id}/convert", [
            'name' => 'Tenant Rollback',
            'email' => 'owner-rollback@example.com',
            'phone' => '11911112222',
        ])->assertStatus(422);

        $lead->refresh();
        $this->assertSame('lead-rollback@example.com', $lead->email);

        $this->assertDatabaseMissing('platform_tenants', [
            'primary_email' => 'owner-rollback@example.com',
        ]);
    }

    public function test_convert_second_attempt_returns_validation_error_due_to_existing_user_email(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin, abilities: ['*']);
        $this->ensureGeneralSegment();

        $lead = $this->createLead([
            'name' => 'Lead Duplicado',
            'email' => 'duplicate-owner@example.com',
            'phone' => '11922223333',
        ]);

        $this->postJson("/api/platform/leads/{$lead->id}/convert", [
            'name' => 'Tenant Primeira Conversão',
            'email' => 'duplicate-owner@example.com',
            'phone' => '11922223333',
        ])->assertOk();

        $this->postJson("/api/platform/leads/{$lead->id}/convert", [
            'name' => 'Tenant Segunda Conversão',
            'email' => 'duplicate-owner@example.com',
            'phone' => '11922223333',
        ])->assertStatus(422);
    }

    public function test_convert_returns_not_found_when_lead_does_not_exist(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin, abilities: ['*']);

        $this->postJson('/api/platform/leads/00000000-0000-4000-8000-000000000000/convert', [
            'name' => 'Tenant Inexistente',
            'email' => 'missing@example.com',
            'phone' => '11900000000',
        ])->assertNotFound();
    }

    public function test_non_admin_cannot_convert_platform_lead(): void
    {
        $user = AuthUser::factory()->create();
        Sanctum::actingAs($user, abilities: ['*']);

        $lead = $this->createLead();

        $this->postJson("/api/platform/leads/{$lead->id}/convert", [
            'name' => 'Tenant Sem Permissão',
            'email' => 'forbidden@example.com',
            'phone' => '11999990000',
        ])->assertStatus(403);
    }

    public function test_convert_validates_required_fields(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin, abilities: ['*']);

        $lead = $this->createLead();

        $this->postJson("/api/platform/leads/{$lead->id}/convert", [
            'name' => '',
            'email' => 'not-an-email',
            'phone' => '',
            'plan_id' => 'invalid-uuid',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'phone', 'plan_id']);
    }

    public function test_admin_can_export_platform_leads_csv(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin, abilities: ['*']);

        $this->createLead([
            'name' => '=Lead Formula',
            'email' => 'lead-export@example.com',
            'lgpd_consent' => true,
        ]);

        $response = $this->get('/api/platform/leads/export');

        $response
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $contentDisposition = (string) $response->headers->get('content-disposition');
        $this->assertStringContainsString('attachment; filename=platform_leads_', $contentDisposition);

        $csv = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('ID;Nome;Email;Telefone;Empresa;', $csv);
        $this->assertStringContainsString('"Consentimento LGPD";"Data de Cadastro";"UTM Source";"UTM Campaign"', $csv);
        $this->assertStringContainsString("'=Lead Formula", $csv);
        $this->assertStringContainsString('lead-export@example.com', $csv);
    }

    public function test_export_applies_search_filter(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin, abilities: ['*']);

        $this->createLead([
            'name' => 'João Exportável',
            'email' => 'joao-export@example.com',
        ]);

        $this->createLead([
            'name' => 'Maria Não Exportável',
            'email' => 'maria-export@example.com',
        ]);

        $response = $this->get('/api/platform/leads/export?search=João');

        $response->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringContainsString('joao-export@example.com', $csv);
        $this->assertStringNotContainsString('maria-export@example.com', $csv);
    }
}
