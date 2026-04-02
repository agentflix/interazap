<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Enums\CRMTaskStatus;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationTask;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeder para CRMNegotiationTask.
 *
 * Cria tarefas vinculadas às negociações existentes no sistema.
 */
final class CRMNegotiationTaskSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * @var array<int, string>
     */
    private const TASK_TITLES = [
        'Agendar reunião de diagnóstico',
        'Enviar proposta comercial',
        'Coletar documentos para aprovação',
        'Follow-up com decisor',
        'Validar integração técnica',
        'Negociar condições de pagamento',
        'Preparar apresentação do produto',
        'Alinhar expectativas com stakeholders',
        'Solicitar referências comerciais',
        'Realizar demo do produto',
        'Elaborar cronograma de implementação',
        'Revisar contrato com jurídico',
        'Agendar kick-off do projeto',
        'Validar requisitos técnicos',
        'Enviar material institucional',
    ];

    /**
     * @var array<int, string>
     */
    private const TASK_DESCRIPTIONS = [
        'Tarefa prioritária para dar andamento no processo comercial.',
        'Ação necessária para avançar a negociação para a próxima etapa.',
        'Item bloqueante que precisa ser resolvido o quanto antes.',
        'Atividade de rotina para manter relacionamento ativo.',
        'Validação técnica necessária antes do fechamento.',
        'Alinhamento estratégico com decisores da empresa.',
        'Preparação de documentação necessária para assinatura.',
        'Reunião técnica para validar requisitos do projeto.',
    ];

    /**
     * Define quantas tarefas criar por negociação.
     */
    private const MIN_TASKS_PER_NEGOTIATION = 1;

    private const MAX_TASKS_PER_NEGOTIATION = 5;

    public function run(): void
    {
        $tenants = PlatformTenant::query()->limit(100)->get();

        if ($tenants->isEmpty()) {
            $this->command->warn('Nenhum tenant encontrado. Execute DatabaseSeeder primeiro.');

            return;
        }

        $totalTasks = 0;

        foreach ($tenants as $tenant) {
            $negotiations = CRMNegotiation::query()
                ->where('tenant_id', $tenant->id)
                ->get();

            if ($negotiations->isEmpty()) {
                $this->command->warn(sprintf('Tenant %s sem negociações. Execute CRMNegotiationSeeder primeiro.', $tenant->id));

                continue;
            }

            $users = AuthUser::query()
                ->where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->get();

            foreach ($negotiations as $negotiation) {
                // Remove tarefas existentes desta negociação (se chamado novamente)
                CRMNegotiationTask::query()
                    ->where('crm_negotiation_id', $negotiation->id)
                    ->delete();

                $taskCount = random_int(
                    self::MIN_TASKS_PER_NEGOTIATION,
                    self::MAX_TASKS_PER_NEGOTIATION
                );

                for ($index = 0; $index < $taskCount; $index++) {
                    $status = $this->getRandomStatus();
                    $dueDate = $this->getDueDateByStatus($status);
                    $assignee = $users->isNotEmpty() ? $users->random() : null;

                    CRMNegotiationTask::query()->create([
                        'id' => (string) Str::orderedUuid(),
                        'tenant_id' => $tenant->id,
                        'crm_negotiation_id' => $negotiation->id,
                        'auth_user_id' => $assignee?->id,
                        'title' => self::TASK_TITLES[array_rand(self::TASK_TITLES)],
                        'description' => self::TASK_DESCRIPTIONS[array_rand(self::TASK_DESCRIPTIONS)],
                        'status' => $status->value,
                        'due_date' => $dueDate,
                    ]);

                    $totalTasks++;
                }
            }
        }

        $this->command->info(sprintf('✅ Tarefas criadas: %d', $totalTasks));
    }

    /**
     * Retorna status aleatório com distribuição realista.
     */
    private function getRandomStatus(): CRMTaskStatus
    {
        $random = random_int(1, 100);

        // 50% pendente, 30% em progresso, 20% concluída
        if ($random <= 50) {
            return CRMTaskStatus::PENDING;
        }

        if ($random <= 80) {
            return CRMTaskStatus::IN_PROGRESS;
        }

        return CRMTaskStatus::DONE;
    }

    /**
     * Define data de vencimento baseada no status.
     */
    private function getDueDateByStatus(CRMTaskStatus $status): \DateTime
    {
        return match ($status) {
            CRMTaskStatus::PENDING => now()->addDays(random_int(1, 15)),
            CRMTaskStatus::IN_PROGRESS => now()->addDays(random_int(1, 7)),
            CRMTaskStatus::DONE => now()->subDays(random_int(1, 30)),
        };
    }
}
