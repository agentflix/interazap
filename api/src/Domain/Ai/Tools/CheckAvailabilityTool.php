<?php

declare(strict_types=1);

namespace Domain\Ai\Tools;

use Carbon\Carbon;
use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\CRM\Models\CRMEvent;

/**
 * Ferramenta de IA para verificar disponibilidade de agenda em um intervalo de datas.
 *
 * Input esperado: date_from e date_to em formato ISO.
 * Output produzido: flag is_available e lista de conflitos encontrados.
 * Quando usar: antes de sugerir ou confirmar horários de eventos para o cliente.
 */
class CheckAvailabilityTool implements AiToolInterface
{
    /** Executa a verificação de conflitos no calendário. */
    public function handle(ToolInputDTO $input): ToolResultDTO
    {
        $dateFrom = (string) ($input->parameters['date_from'] ?? '');
        $dateTo = (string) ($input->parameters['date_to'] ?? '');
        $tenantId = (string) ($input->context['tenant_id'] ?? '');

        if ($dateFrom === '' || $dateTo === '') {
            return ToolResultDTO::failure('date_from and date_to are required');
        }

        try {
            $from = Carbon::parse($dateFrom);
            $to = Carbon::parse($dateTo);
        } catch (\Throwable) {
            return ToolResultDTO::failure('Invalid date format');
        }

        if ($to->lessThanOrEqualTo($from)) {
            return ToolResultDTO::failure('date_to must be greater than date_from');
        }

        $conflicts = CRMEvent::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($builder) use ($from, $to): void {
                $builder
                    ->whereBetween('starts_at', [$from, $to])
                    ->orWhereBetween('ends_at', [$from, $to])
                    ->orWhere(function ($inner) use ($from, $to): void {
                        $inner->where('starts_at', '<=', $from)
                            ->where('ends_at', '>=', $to);
                    });
            })
            ->orderBy('starts_at')
            ->get();

        return ToolResultDTO::success(
            message: $conflicts->isEmpty() ? 'No conflicts found' : 'Conflicts found in selected range',
            data: [
                'is_available' => $conflicts->isEmpty(),
                'conflicts' => $conflicts->map(static fn (CRMEvent $event): array => [
                    'id' => $event->id,
                    'title' => $event->title,
                    'starts_at' => $event->starts_at?->toIso8601String(),
                    'ends_at' => $event->ends_at?->toIso8601String(),
                    'type' => $event->type,
                    'status' => $event->status,
                ])->values()->all(),
            ]
        );
    }

    /** Retorna o nome único da ferramenta. */
    public function getName(): string
    {
        return \Domain\Ai\Enums\AiToolEnum::CHECK_AVAILABILITY;
    }

    /** Retorna a descrição da ferramenta para o LLM. */
    public function getDescription(): string
    {
        return 'Checks event conflicts and availability within a datetime range.';
    }

    /**
     * Retorna os parâmetros esperados pela ferramenta.
     *
     * @return array<string, array{type: string, required: bool, description: string}>
     */
    public function getParameters(): array
    {
        return [
            'date_from' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Range start datetime',
            ],
            'date_to' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Range end datetime',
            ],
        ];
    }
}
