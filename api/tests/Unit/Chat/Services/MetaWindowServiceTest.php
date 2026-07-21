<?php

declare(strict_types=1);

namespace Tests\Unit\Chat\Services;

use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Services\MetaWindowService;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

/**
 * Testes unitários para MetaWindowService.
 *
 * Cobre a semântica oficial da janela de atendimento Meta:
 * - reabertura em 24h a partir do inbound;
 * - abertura de 72h quando há referral (CTWA);
 * - GREATEST (janela nunca encurta) e preservação do tipo 72h;
 * - guard de escrita redundante (save() só quando algo muda);
 * - fallback para now() quando o timestamp está ausente/inválido.
 */
class MetaWindowServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private MetaWindowService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MetaWindowService;
    }

    /**
     * Garante que `Carbon::setTestNow()` NUNCA vaze para outros arquivos de
     * teste. Os testes que congelam o relógio já fazem `Carbon::setTestNow()`
     * (sem args) no fim do próprio corpo, mas se uma asserção anterior falhar
     * (lançar exceção), essa linha final nunca é executada e o relógio
     * congelado escapa para TODOS os testes seguintes no mesmo processo —
     * exatamente o tipo de poluição de estado entre testes que dependeria da
     * ordem de execução. O `tearDown()` é a rede de segurança incondicional.
     */
    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    private function makeTicket(array $attributes = []): ChatTicket
    {
        $tenant = PlatformTenant::factory()->create();

        return ChatTicket::factory()->create([
            'tenant_id' => $tenant->id,
            ...$attributes,
        ]);
    }

    public function test_closed_window_reopens_at_24h_from_message_timestamp(): void
    {
        $ticket = $this->makeTicket([
            'meta_window_expires_at' => Date::now()->subHours(2),
            'meta_window_type' => '24h',
        ]);

        $messageTimestamp = Date::parse('2026-07-21T10:00:00Z')->toIso8601String();

        $this->service->renewFromInbound($ticket, $messageTimestamp, null);

        $ticket->refresh();

        $this->assertSame('24h', $ticket->meta_window_type);
        $this->assertTrue(
            $ticket->meta_window_expires_at->equalTo(Date::parse('2026-07-21T10:00:00Z')->addHours(24))
        );
    }

    public function test_referral_opens_72h_window_and_persists_referral_fields(): void
    {
        $ticket = $this->makeTicket();

        $messageTimestamp = Date::parse('2026-07-21T10:00:00Z')->toIso8601String();
        $referral = [
            'source_id' => 'ad-123',
            'source_type' => 'ad',
            'headline' => 'Promoção de inverno',
            'ctwa_clid' => 'clid-abc',
        ];

        $this->service->renewFromInbound($ticket, $messageTimestamp, $referral);

        $ticket->refresh();

        $this->assertSame('72h', $ticket->meta_window_type);
        $this->assertTrue(
            $ticket->meta_window_expires_at->equalTo(Date::parse('2026-07-21T10:00:00Z')->addHours(72))
        );
        $this->assertSame('ad-123', $ticket->meta_referral_source_id);
        $this->assertSame('ad', $ticket->meta_referral_source_type);
        $this->assertSame('Promoção de inverno', $ticket->meta_referral_headline);
        $this->assertSame('clid-abc', $ticket->meta_referral_ctwa_clid);
    }

    public function test_valid_72h_window_is_not_downgraded_to_24h_by_new_inbound(): void
    {
        $referralBase = Date::parse('2026-07-21T10:00:00Z');
        $ticket = $this->makeTicket([
            'meta_window_expires_at' => $referralBase->copy()->addHours(72),
            'meta_window_type' => '72h',
        ]);

        // Inbound simples e sem referral, uma hora depois — a janela de 24h
        // resultante (base+24h) é MENOR que a janela de 72h vigente.
        $nextMessageTimestamp = $referralBase->copy()->addHour()->toIso8601String();

        $this->service->renewFromInbound($ticket, $nextMessageTimestamp, null);

        $ticket->refresh();

        $this->assertSame('72h', $ticket->meta_window_type);
        $this->assertTrue(
            $ticket->meta_window_expires_at->equalTo($referralBase->copy()->addHours(72))
        );
    }

    public function test_identical_value_does_not_trigger_save(): void
    {
        $timestamp = Date::parse('2026-07-21T10:00:00Z');
        $ticket = $this->makeTicket([
            'meta_window_expires_at' => $timestamp->copy()->addHours(24),
            'meta_window_type' => '24h',
        ]);
        $ticket->refresh();
        $originalUpdatedAt = $ticket->updated_at;

        // Avança o relógio para garantir que um save() real mudaria updated_at
        // caso a query fosse de fato disparada.
        Date::setTestNow($timestamp->copy()->addMinute());

        // Evidência dura: espiona as queries SQL reais disparadas contra o
        // banco. Uma renovação com o MESMO valor não pode gerar nenhum
        // UPDATE em chat_tickets — isso é o que evita amplificação de
        // escrita e disparo de eventos de realtime redundantes.
        \Illuminate\Support\Facades\DB::enableQueryLog();
        \Illuminate\Support\Facades\DB::flushQueryLog();

        $this->service->renewFromInbound($ticket, $timestamp->toIso8601String(), null);

        $queryLog = \Illuminate\Support\Facades\DB::getQueryLog();
        \Illuminate\Support\Facades\DB::disableQueryLog();

        $updateQueriesOnChatTickets = array_filter(
            $queryLog,
            fn (array $entry): bool => str_starts_with(strtolower((string) $entry['query']), 'update')
                && str_contains(strtolower((string) $entry['query']), 'chat_tickets')
        );

        $this->assertCount(
            0,
            $updateQueriesOnChatTickets,
            'renewFromInbound() com valor idêntico não deve disparar nenhum UPDATE em chat_tickets. Queries: '
                .json_encode(array_values($updateQueriesOnChatTickets))
        );

        $ticket->refresh();
        $this->assertTrue($originalUpdatedAt->equalTo($ticket->updated_at));

        Date::setTestNow();
    }

    public function test_changed_value_does_trigger_a_single_update_on_chat_tickets(): void
    {
        // Contraprova do teste acima: quando o valor de fato muda, DEVE
        // haver exatamente 1 UPDATE em chat_tickets (nunca zero, nunca dois
        // — ver regra 6: janela e referral gravados na MESMA operação).
        $timestamp = Date::parse('2026-07-21T10:00:00Z');
        $ticket = $this->makeTicket([
            'meta_window_expires_at' => Date::now()->subHours(2),
            'meta_window_type' => '24h',
        ]);

        \Illuminate\Support\Facades\DB::enableQueryLog();
        \Illuminate\Support\Facades\DB::flushQueryLog();

        $this->service->renewFromInbound($ticket, $timestamp->toIso8601String(), [
            'ctwa_clid' => 'clid-1',
        ]);

        $queryLog = \Illuminate\Support\Facades\DB::getQueryLog();
        \Illuminate\Support\Facades\DB::disableQueryLog();

        $updateQueriesOnChatTickets = array_values(array_filter(
            $queryLog,
            fn (array $entry): bool => str_starts_with(strtolower((string) $entry['query']), 'update')
                && str_contains(strtolower((string) $entry['query']), 'chat_tickets')
        ));

        $this->assertCount(1, $updateQueriesOnChatTickets);
    }

    public function test_missing_or_invalid_timestamp_falls_back_to_now(): void
    {
        Date::setTestNow(Date::parse('2026-07-21T12:00:00Z'));

        $ticketMissing = $this->makeTicket();
        $this->service->renewFromInbound($ticketMissing, null, null);
        $ticketMissing->refresh();

        $this->assertTrue(
            $ticketMissing->meta_window_expires_at->equalTo(Date::now()->addHours(24))
        );

        $ticketInvalid = $this->makeTicket();
        $this->service->renewFromInbound($ticketInvalid, 'not-a-valid-timestamp', null);
        $ticketInvalid->refresh();

        $this->assertTrue(
            $ticketInvalid->meta_window_expires_at->equalTo(Date::now()->addHours(24))
        );

        Date::setTestNow();
    }

    public function test_apply_from_status_never_shortens_the_window(): void
    {
        $ticket = $this->makeTicket([
            'meta_window_expires_at' => Date::parse('2026-07-22T00:00:00Z'),
            'meta_window_type' => '24h',
        ]);

        // Status reporta uma expiração MENOR que a vigente — não deve encurtar.
        $this->service->applyFromStatus($ticket, Date::parse('2026-07-21T18:00:00Z')->toIso8601String(), '24h');

        $ticket->refresh();

        $this->assertTrue($ticket->meta_window_expires_at->equalTo(Date::parse('2026-07-22T00:00:00Z')));
    }

    public function test_apply_from_status_with_invalid_timestamp_is_a_noop_on_ticket_without_prior_window(): void
    {
        // Ticket SEM janela prévia: se um expiresAtIso inválido caísse em
        // now(), o GREATEST não teria nada para comparar e a janela
        // nasceria já expirada. O correto é ignorar (no-op).
        $ticket = $this->makeTicket();

        $this->service->applyFromStatus($ticket, 'not-a-valid-timestamp', '24h');
        $ticket->refresh();
        $this->assertNull($ticket->meta_window_expires_at);
        $this->assertNull($ticket->meta_window_type);

        $this->service->applyFromStatus($ticket, '', '72h');
        $ticket->refresh();
        $this->assertNull($ticket->meta_window_expires_at);
        $this->assertNull($ticket->meta_window_type);
    }

    public function test_apply_from_status_with_invalid_window_type_is_a_noop(): void
    {
        // windowType fora de '24h'/'72h' violaria o CHECK constraint da
        // coluna — deve ser rejeitado ANTES de tentar persistir.
        $ticket = $this->makeTicket();

        $this->service->applyFromStatus($ticket, Date::now()->addHours(24)->toIso8601String(), '48h');

        $ticket->refresh();

        $this->assertNull($ticket->meta_window_expires_at);
        $this->assertNull($ticket->meta_window_type);
    }

    public function test_apply_from_status_with_invalid_window_type_does_not_downgrade_existing_window(): void
    {
        $expiresAt = Date::parse('2026-07-22T00:00:00Z');
        $ticket = $this->makeTicket([
            'meta_window_expires_at' => $expiresAt,
            'meta_window_type' => '72h',
        ]);

        $this->service->applyFromStatus($ticket, Date::now()->addHours(1)->toIso8601String(), 'invalid');

        $ticket->refresh();

        $this->assertTrue($ticket->meta_window_expires_at->equalTo($expiresAt));
        $this->assertSame('72h', $ticket->meta_window_type);
    }
}
