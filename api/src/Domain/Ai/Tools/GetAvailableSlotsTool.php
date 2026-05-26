<?php

declare(strict_types=1);

namespace Domain\Ai\Tools;

use Carbon\Carbon;
use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\Configuration\Models\ConfigurationOpeningHour;
use Domain\CRM\Models\CRMEvent;

/**
 * Ferramenta de IA para encontrar horários disponíveis para agendamento.
 *
 * Input esperado: date_from, date_to em ISO, duration_minutes e user_id opcionais.
 * Output produzido: lista de até 8 slots livres com starts_at e ends_at.
 * Quando usar: sugerir horários ao cliente antes de criar um evento no CRM.
 * Respeita o horário de funcionamento do tenant e exclui eventos existentes.
 */
class GetAvailableSlotsTool implements AiToolInterface
{
    /** Executa a busca por horários disponíveis. */
    public function handle(ToolInputDTO $input): ToolResultDTO
    {
        $dateFrom = (string) ($input->parameters['date_from'] ?? '');
        $dateTo = (string) ($input->parameters['date_to'] ?? '');
        $durationMinutes = (int) ($input->parameters['duration_minutes'] ?? 30);
        $userId = (string) ($input->parameters['user_id'] ?? '');
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

        if ($durationMinutes < 15 || $durationMinutes > 480) {
            return ToolResultDTO::failure('duration_minutes must be between 15 and 480');
        }

        // Fetch opening hours for the tenant
        $openingHoursRaw = ConfigurationOpeningHour::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get();

        $openingHours = $openingHoursRaw->groupBy('day_of_week');
        $noHoursConfigured = $openingHoursRaw->isEmpty();

        // Fetch existing events in the period
        $eventsQuery = CRMEvent::query()
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', CRMEvent::STATUS_CANCELLED)
            ->where(function ($builder) use ($from, $to): void {
                $builder
                    ->whereBetween('starts_at', [$from, $to])
                    ->orWhereBetween('ends_at', [$from, $to])
                    ->orWhere(function ($inner) use ($from, $to): void {
                        $inner->where('starts_at', '<=', $from)
                            ->where('ends_at', '>=', $to);
                    });
            });

        if ($userId !== '') {
            $eventsQuery->where('auth_user_id', $userId);
        }

        $existingEvents = $eventsQuery->orderBy('starts_at')->get();

        // Generate available slots
        $slots = [];
        $current = $from->copy();
        $maxSlots = 8;

        while ($current->lessThan($to) && count($slots) < $maxSlots) {
            $dayOfWeek = $current->dayOfWeek;
            $slotEnd = $current->copy()->addMinutes($durationMinutes);

            // Check if within opening hours (skip when none configured)
            if ($noHoursConfigured || $this->isWithinOpeningHours($current, $slotEnd, $dayOfWeek, $openingHours)) {
                // Check for conflicts with existing events
                if (! $this->hasConflict($current, $slotEnd, $existingEvents)) {
                    $slots[] = [
                        'starts_at' => $current->toIso8601String(),
                        'ends_at' => $slotEnd->toIso8601String(),
                    ];
                }
            }

            $current->addMinutes($durationMinutes);
        }

        return ToolResultDTO::success(
            message: count($slots) > 0 ? 'Available slots found' : 'No available slots in the selected range',
            data: [
                'slots' => $slots,
                'total' => count($slots),
            ]
        );
    }

    /**
     * Verifica se um slot está dentro do horário de funcionamento.
     *
     * @param  Carbon  $start  Início do slot.
     * @param  Carbon  $end  Fim do slot.
     * @param  int  $dayOfWeek  Dia da semana (0=Domingo, 6=Sábado).
     * @param  mixed  $openingHours  Horários de funcionamento agrupados por dia.
     */
    private function isWithinOpeningHours(Carbon $start, Carbon $end, int $dayOfWeek, mixed $openingHours): bool
    {
        $hoursForDay = $openingHours->get($dayOfWeek, []);

        if ($hoursForDay->isEmpty()) {
            return false;
        }

        $startTime = $start->format('H:i');
        $endTime = $end->format('H:i');

        foreach ($hoursForDay as $hour) {
            $openTime = $hour->open_time;
            $closeTime = $hour->close_time;

            if ($startTime >= $openTime && $endTime <= $closeTime) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verifica se um slot conflita com eventos existentes.
     *
     * @param  Carbon  $start  Início do slot.
     * @param  Carbon  $end  Fim do slot.
     * @param  mixed  $events  Eventos existentes.
     */
    private function hasConflict(Carbon $start, Carbon $end, mixed $events): bool
    {
        foreach ($events as $event) {
            if (! $event->starts_at || ! $event->ends_at) {
                continue;
            }

            $eventStart = Carbon::instance($event->starts_at);
            $eventEnd = Carbon::instance($event->ends_at);

            // Overlap check: two intervals overlap if one starts before the other ends
            if ($start->lessThan($eventEnd) && $end->greaterThan($eventStart)) {
                return true;
            }
        }

        return false;
    }

    /** Retorna o nome único da ferramenta. */
    public function getName(): string
    {
        return \Domain\Ai\Enums\AiToolEnum::GET_AVAILABLE_SLOTS;
    }

    /** Retorna a descrição da ferramenta para o LLM. */
    public function getDescription(): string
    {
        return 'Returns available calendar slots within a date range, respecting opening hours and excluding existing events.';
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
                'description' => 'Start of the date range in ISO format',
            ],
            'date_to' => [
                'type' => 'string',
                'required' => true,
                'description' => 'End of the date range in ISO format',
            ],
            'duration_minutes' => [
                'type' => 'integer',
                'required' => false,
                'description' => 'Duration of each slot in minutes (default: 30)',
            ],
            'user_id' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Optional user UUID to filter by operator availability',
            ],
        ];
    }
}
