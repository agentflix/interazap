<?php

declare(strict_types=1);

namespace Domain\Configuration\Actions;

use Domain\Configuration\Models\ConfigurationSchedulingSetting;

/**
 * Casos de uso para Configurações de Agendamento.
 *
 * Centraliza a leitura e atualização das configurações
 * de confirmação de eventos por tenant.
 *
 * @category Actions
 */
final class ConfigurationSchedulingSettingActions
{
    /**
     * Buscar configurações de um tenant.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @return ConfigurationSchedulingSetting Configuração encontrada ou criada com valores padrão.
     */
    public function get(string $tenantId): ConfigurationSchedulingSetting
    {
        return ConfigurationSchedulingSetting::forTenant($tenantId);
    }

    /**
     * Atualizar configurações de agendamento do tenant.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  array<string, mixed>  $data  Atributos para atualizar.
     * @return ConfigurationSchedulingSetting Configuração atualizada.
     */
    public function update(string $tenantId, array $data): ConfigurationSchedulingSetting
    {
        $setting = $this->get($tenantId);

        $setting->fill($data);
        $setting->save();

        return $setting;
    }
}
