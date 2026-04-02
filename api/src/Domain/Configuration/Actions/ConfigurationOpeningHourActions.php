<?php

declare(strict_types=1);

namespace Domain\Configuration\Actions;

use Domain\Configuration\Models\ConfigurationOpeningHour;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Casos de uso e orquestração para Horários de Atendimento.
 *
 * Centraliza a lógica de disponibilidade por tenant, permitindo gerenciar
 * múltiplos intervalos de tempo e realizar verificações de "Aberto/Fechado"
 * em tempo real.
 *
 * @category Actions
 */
final class ConfigurationOpeningHourActions
{
    /**
     * Listar todos os horários de um tenant.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @return Collection<int, ConfigurationOpeningHour> Coleção de horários ordenados por dia.
     */
    public function list(string $tenantId): Collection
    {
        return ConfigurationOpeningHour::query()
            ->forTenant($tenantId)
            ->orderBy('day_of_week')
            ->get();
    }

    /**
     * Criar um novo registro de horário individual.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  array<string, mixed>  $data  Atributos do horário.
     * @return ConfigurationOpeningHour Registro persistido.
     */
    public function create(string $tenantId, array $data): ConfigurationOpeningHour
    {
        return ConfigurationOpeningHour::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            ...$data,
        ]);
    }

    /**
     * Atualizar dados de um horário específico.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $id  Identificador UUID.
     * @param  array<string, mixed>  $data  Novos atributos.
     * @return ConfigurationOpeningHour Registro atualizado.
     */
    public function update(string $tenantId, string $id, array $data): ConfigurationOpeningHour
    {
        $hour = $this->find($tenantId, $id);
        $hour->fill($data);
        $hour->save();

        return $hour;
    }

    /**
     * Remover um horário.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $id  Identificador UUID.
     */
    public function delete(string $tenantId, string $id): void
    {
        $hour = $this->find($tenantId, $id);
        $hour->delete();
    }

    /**
     * Substituir em massa todos os horários do tenant.
     *
     * Limpa a configuração anterior e registra o novo conjunto de regras
     * dentro de uma transação atômica.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  array<int, array<string, mixed>>  $openingHours  Lista de horários.
     * @return Collection<int, ConfigurationOpeningHour> Nova coleção persistida.
     */
    public function bulkReplace(string $tenantId, array $openingHours): Collection
    {
        return DB::transaction(function () use ($tenantId, $openingHours): Collection {
            ConfigurationOpeningHour::query()->forTenant($tenantId)->delete();

            $created = new Collection;
            foreach ($openingHours as $hour) {
                $created->push($this->create($tenantId, [
                    'day_of_week' => $hour['day_of_week'],
                    'open_time' => $hour['open_time'],
                    'close_time' => $hour['close_time'],
                    'is_active' => $hour['is_active'] ?? true,
                ]));
            }

            return $created;
        });
    }

    /**
     * Verificar se o tenant está com atendimento ativo no momento.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @return array{is_open: bool, current_day: int, current_time: string, opening_hour: ?ConfigurationOpeningHour}
     */
    public function isOpen(string $tenantId): array
    {
        $now = now();
        $currentDay = $now->dayOfWeek;
        $currentTime = $now->format('H:i');

        $openingHour = ConfigurationOpeningHour::query()
            ->forTenant($tenantId)
            ->active()
            ->where('day_of_week', $currentDay)
            ->where('open_time', '<=', $currentTime)
            ->where('close_time', '>=', $currentTime)
            ->first();

        return [
            'is_open' => $openingHour !== null,
            'current_day' => $currentDay,
            'current_time' => $currentTime,
            'opening_hour' => $openingHour,
        ];
    }

    /**
     * Buscar um horário específico por tenant e ID.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $id  Identificador UUID do horário.
     * @return ConfigurationOpeningHour Registro encontrado.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function find(string $tenantId, string $id): ConfigurationOpeningHour
    {
        return ConfigurationOpeningHour::query()
            ->forTenant($tenantId)
            ->findOrFail($id);
    }
}
