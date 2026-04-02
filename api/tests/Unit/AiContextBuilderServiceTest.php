<?php

declare(strict_types=1);

namespace Tests\Unit;

use Domain\Ai\Services\AiContextBuilderService;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Domain\CRM\Actions\CRMContactActions;
use Domain\CRM\Models\CRMCompany;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Domain\CRM\Models\CRMTag;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiContextBuilderServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private CRMContactActions $contactActions;

    private AiContextBuilderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->contactActions = new CRMContactActions;
        $this->service = new AiContextBuilderService($this->contactActions);
    }

    public function test_builds_context_with_history_and_contact_data(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $contact = CRMContact::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Maria Silva',
            'email' => 'maria@example.com',
            'phone' => '5511999999999',
            'whatsapp' => '5511999999999',
        ]);

        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $tenant->id,
            'contact_id' => $contact->id,
            'protocol' => 'PR-001',
            'priority' => 'high',
            'category' => 'support',
            'push_name' => 'Maria',
            'phone_e164' => '5511999999999',
            'status' => 'pending',
            'subject' => 'Ajuda',
        ]);

        $incoming = ChatMessage::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'content' => 'Olá',
            'type' => 'text',
            'direction' => 'incoming',
            'is_from_contact' => true,
            'status' => 'received',
        ]);

        ChatMessage::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'content' => 'Como posso ajudar?',
            'type' => 'text',
            'direction' => 'outgoing',
            'is_from_contact' => false,
            'status' => 'sent',
        ]);

        ChatMessage::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'content' => null,
            'type' => 'file',
            'direction' => 'incoming',
            'is_from_contact' => true,
            'status' => 'received',
            'file_name' => 'manual.pdf',
        ]);

        $context = $this->service->build($ticket, $incoming);

        $this->assertSame('PR-001', $context['ticket']['protocol']);
        $this->assertSame('high', $context['ticket']['priority']);
        $this->assertSame('support', $context['ticket']['department']);
        $this->assertSame('Maria Silva', $context['contact']['name']);
        $this->assertSame('5511999999999', $context['contact']['phone']);
        $this->assertSame('Olá', $context['current_input']);

        $this->assertContains('User: Olá', $context['conversation_history']);
        $this->assertContains('Agent: Como posso ajudar?', $context['conversation_history']);
        $this->assertTrue(
            collect($context['conversation_history'])->contains(fn (string $line): bool => str_contains($line, 'Arquivo enviado'))
        );
    }

    public function test_crm_summary_with_contact_and_deals(): void
    {
        $tenant = PlatformTenant::factory()->create();

        $company = CRMCompany::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Acme Corp',
        ]);

        $contact = CRMContact::factory()->create([
            'tenant_id' => $tenant->id,
            'crm_company_id' => $company->id,
            'name' => 'João Cliente VIP',
            'phone' => '5511988887777',
            'whatsapp' => '5511988887777',
        ]);

        // Criar tags
        $tagVip = CRMTag::factory()->create(['tenant_id' => $tenant->id, 'name' => 'VIP']);
        $tagPremium = CRMTag::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Premium']);

        // Associar tags com UUID explícito para tabela pivot
        DB::table('crm_contact_tags')->insert([
            [
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $tenant->id,
                'crm_contact_id' => $contact->id,
                'crm_tag_id' => $tagVip->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $tenant->id,
                'crm_contact_id' => $contact->id,
                'crm_tag_id' => $tagPremium->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Criar funil e etapa
        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $tenant->id]);
        $step = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $tenant->id,
            'crm_negotiation_funnel_id' => $funnel->id,
            'name' => 'Proposta Enviada',
        ]);

        // Criar negociação aberta
        CRMNegotiation::factory()->create([
            'tenant_id' => $tenant->id,
            'crm_contact_id' => $contact->id,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step->id,
            'title' => 'Projeto Website',
            'amount' => 15000.50,
            'status' => 'open',
        ]);

        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $tenant->id,
            'contact_id' => $contact->id,
            'phone_e164' => '5511988887777',
        ]);

        $message = ChatMessage::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'content' => 'Olá, qual o status?',
            'type' => 'text',
            'direction' => 'incoming',
            'is_from_contact' => true,
            'status' => 'received',
        ]);

        $context = $this->service->build($ticket, $message);

        $this->assertArrayHasKey('user_context', $context);
        $userContext = $context['user_context'];

        // Verificar identificação
        $this->assertStringContainsString('Cliente Identificado: João Cliente VIP', $userContext);
        $this->assertStringContainsString('Empresa: Acme Corp', $userContext);

        // Verificar tags
        $this->assertStringContainsString('Perfil:', $userContext);
        $this->assertStringContainsString('VIP', $userContext);
        $this->assertStringContainsString('Premium', $userContext);

        // Verificar negociação
        $this->assertStringContainsString('Oportunidades em aberto:', $userContext);
        $this->assertStringContainsString('Projeto Website', $userContext);
        $this->assertStringContainsString('Proposta Enviada', $userContext);
        $this->assertStringContainsString('15.000,50', $userContext);
    }

    public function test_crm_summary_with_contact_without_deals(): void
    {
        $tenant = PlatformTenant::factory()->create();

        $contact = CRMContact::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Maria Sem Negociação',
            'phone' => '5511977776666',
            'whatsapp' => '5511977776666',
        ]);

        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $tenant->id,
            'contact_id' => $contact->id,
            'phone_e164' => '5511977776666',
        ]);

        $message = ChatMessage::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'content' => 'Preciso de ajuda',
            'type' => 'text',
            'direction' => 'incoming',
            'is_from_contact' => true,
            'status' => 'received',
        ]);

        $context = $this->service->build($ticket, $message);

        $userContext = $context['user_context'];

        // Deve ter identificação
        $this->assertStringContainsString('Cliente Identificado: Maria Sem Negociação', $userContext);

        // NÃO deve ter seção de oportunidades
        $this->assertStringNotContainsString('Oportunidades em aberto:', $userContext);
    }

    public function test_crm_summary_for_unknown_client(): void
    {
        $tenant = PlatformTenant::factory()->create();

        // Ticket SEM contact_id e com telefone que não existe no CRM
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $tenant->id,
            'contact_id' => null,
            'phone_e164' => '5511966665555',
            'push_name' => 'Novo Lead WhatsApp',
        ]);

        $message = ChatMessage::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'content' => 'Oi, quero saber mais',
            'type' => 'text',
            'direction' => 'incoming',
            'is_from_contact' => true,
            'status' => 'received',
        ]);

        $context = $this->service->build($ticket, $message);

        $userContext = $context['user_context'];

        // Deve indicar cliente desconhecido com push_name
        $this->assertStringContainsString('Cliente Desconhecido (Novo Lead)', $userContext);
        $this->assertStringContainsString('Novo Lead WhatsApp', $userContext);
    }

    public function test_crm_summary_for_unknown_client_without_push_name(): void
    {
        $tenant = PlatformTenant::factory()->create();

        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $tenant->id,
            'contact_id' => null,
            'phone_e164' => '5511955554444',
            'push_name' => null,
        ]);

        $message = ChatMessage::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'content' => 'Oi',
            'type' => 'text',
            'direction' => 'incoming',
            'is_from_contact' => true,
            'status' => 'received',
        ]);

        $context = $this->service->build($ticket, $message);

        $userContext = $context['user_context'];

        // Apenas "Cliente Desconhecido (Novo Lead)" sem nome adicional
        $this->assertSame('Cliente Desconhecido (Novo Lead)', $userContext);
    }

    public function test_resolves_contact_by_phone_when_not_linked(): void
    {
        $tenant = PlatformTenant::factory()->create();

        CRMContact::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Encontrado pelo Telefone',
            'phone' => '5511944443333',
            'whatsapp' => '5511944443333',
        ]);

        // Ticket SEM contact_id mas COM telefone que existe no CRM
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $tenant->id,
            'contact_id' => null,
            'phone_e164' => '5511944443333',
            'push_name' => 'Qualquer Nome',
        ]);

        $message = ChatMessage::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'content' => 'Mensagem teste',
            'type' => 'text',
            'direction' => 'incoming',
            'is_from_contact' => true,
            'status' => 'received',
        ]);

        $context = $this->service->build($ticket, $message);

        $userContext = $context['user_context'];

        // Deve encontrar o contato pelo telefone
        $this->assertStringContainsString('Cliente Identificado: Encontrado pelo Telefone', $userContext);
        $this->assertSame('Encontrado pelo Telefone', $context['contact']['name']);
    }
}
