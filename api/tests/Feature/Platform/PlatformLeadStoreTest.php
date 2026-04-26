<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use Domain\Platform\Events\PlatformLeadCreated;
use Domain\Platform\Models\PlatformLead;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Cobertura do endpoint público POST /api/public/leads.
 */
class PlatformLeadStoreTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Payload mínimo válido reutilizado nos testes.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'João da Silva',
            'phone' => '(11) 91234-5678',
            'email' => 'joao@example.com',
            'company' => 'Acme Ltda',
            'source' => 'landing_form',
            'lgpd_consent' => true,
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'launch',
        ], $overrides);
    }

    public function test_creates_valid_lead_and_persists_in_db(): void
    {
        Event::fake([PlatformLeadCreated::class]);

        $response = $this->postJson('/api/public/leads', $this->validPayload());

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'name', 'source', 'created_at']])
            ->assertJsonPath('data.name', 'João da Silva')
            ->assertJsonPath('data.source', 'landing_form');

        // Resposta NÃO expõe email/phone
        $response->assertJsonMissingPath('data.email');
        $response->assertJsonMissingPath('data.phone');

        $this->assertDatabaseHas('platform_leads', [
            'email' => 'joao@example.com',
            'phone' => '11912345678',
            'source' => 'landing_form',
            'utm_source' => 'google',
            'lgpd_consent' => true,
            'status' => 'new',
        ]);

        Event::assertDispatched(PlatformLeadCreated::class, function (PlatformLeadCreated $event): bool {
            return $event->source === 'landing_form' && $event->leadId !== '';
        });
    }

    public function test_rejects_request_without_lgpd_consent(): void
    {
        $payload = $this->validPayload(['lgpd_consent' => false]);

        $this->postJson('/api/public/leads', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lgpd_consent']);

        $this->assertDatabaseCount('platform_leads', 0);
    }

    public function test_rejects_invalid_email(): void
    {
        $payload = $this->validPayload(['email' => 'not-an-email']);

        $this->postJson('/api/public/leads', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_rejects_phone_outside_brazilian_format(): void
    {
        $payload = $this->validPayload(['phone' => '+1-555-0000']);

        $this->postJson('/api/public/leads', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    /**
     * @dataProvider phoneFormatProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('phoneFormatProvider')]
    public function test_accepts_phone_with_or_without_mask_and_persists_only_digits(string $input, string $expected): void
    {
        Event::fake([PlatformLeadCreated::class]);

        $payload = $this->validPayload([
            'phone' => $input,
            'email' => 'normalize-'.md5($input).'@example.com',
        ]);

        $this->postJson('/api/public/leads', $payload)->assertCreated();

        $this->assertDatabaseHas('platform_leads', [
            'phone' => $expected,
        ]);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function phoneFormatProvider(): array
    {
        return [
            'mascarado com 9' => ['(11) 91234-5678', '11912345678'],
            'sem máscara com 9' => ['11912345678', '11912345678'],
            'com espaço sem hifen' => ['11 91234 5678', '11912345678'],
            'fixo sem 9' => ['1132345678', '1132345678'],
        ];
    }

    public function test_honeypot_filled_returns_201_but_does_not_persist(): void
    {
        Event::fake([PlatformLeadCreated::class]);

        $payload = $this->validPayload(['website' => 'http://spammer.example.com']);

        $this->postJson('/api/public/leads', $payload)
            ->assertCreated()
            ->assertJsonPath('data.source', 'landing_form');

        $this->assertDatabaseCount('platform_leads', 0);
        Event::assertNotDispatched(PlatformLeadCreated::class);
    }

    public function test_duplicate_within_24h_does_not_create_second_record(): void
    {
        Event::fake([PlatformLeadCreated::class]);

        $payload = $this->validPayload();

        $this->postJson('/api/public/leads', $payload)->assertCreated();
        $this->postJson('/api/public/leads', $payload)->assertCreated();

        $this->assertDatabaseCount('platform_leads', 1);
        Event::assertDispatchedTimes(PlatformLeadCreated::class, 1);
    }

    public function test_duplicate_by_phone_within_24h_does_not_create_second_record(): void
    {
        Event::fake([PlatformLeadCreated::class]);

        $first = $this->validPayload();
        $second = $this->validPayload([
            'email' => 'outro@example.com',
            // Mesmo telefone normalizado
            'phone' => '11912345678',
        ]);

        $this->postJson('/api/public/leads', $first)->assertCreated();
        $this->postJson('/api/public/leads', $second)->assertCreated();

        $this->assertDatabaseCount('platform_leads', 1);
    }

    public function test_endpoint_works_without_authentication(): void
    {
        // Sem actingAs / sem token Sanctum.
        $response = $this->postJson('/api/public/leads', $this->validPayload([
            'email' => 'noauth@example.com',
        ]));

        $response->assertCreated();
        $this->assertDatabaseHas('platform_leads', ['email' => 'noauth@example.com']);
    }

    public function test_persists_request_metadata_ip_and_user_agent(): void
    {
        Event::fake([PlatformLeadCreated::class]);

        $this->postJson(
            '/api/public/leads',
            $this->validPayload(['email' => 'meta@example.com']),
            ['User-Agent' => 'PestTester/1.0', 'Referer' => 'https://landing.example.com/promo']
        )->assertCreated();

        /** @var PlatformLead $lead */
        $lead = PlatformLead::query()->where('email', 'meta@example.com')->firstOrFail();

        $this->assertNotNull($lead->ip_address);
        $this->assertSame('PestTester/1.0', $lead->user_agent);
        $this->assertSame('https://landing.example.com/promo', $lead->referrer);
    }
}
