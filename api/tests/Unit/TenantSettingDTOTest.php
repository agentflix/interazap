<?php

declare(strict_types=1);

namespace Tests\Unit;

use Domain\Platform\DTOs\TenantSettingDTO;
use PHPUnit\Framework\TestCase;

class TenantSettingDTOTest extends TestCase
{
    public function test_from_array_creates_dto_with_all_defaults(): void
    {
        $dto = TenantSettingDTO::fromArray([]);

        $this->assertEquals('America/Sao_Paulo', $dto->settings_localization['timezone']);
        $this->assertEquals('DD/MM/YYYY', $dto->settings_localization['dateFormat']);
        $this->assertEquals('24h', $dto->settings_localization['timeFormat']);
        $this->assertEquals('BRL', $dto->settings_localization['currencyFormat']);
        $this->assertEquals('team', $dto->settings_privacy['presence']);
        $this->assertTrue($dto->settings_privacy['readReceipt']);
        $this->assertTrue($dto->settings_privacy['notificationPreview']);
    }

    public function test_from_array_applies_partial_overrides(): void
    {
        $dto = TenantSettingDTO::fromArray([
            'settings_localization' => [
                'timezone' => 'UTC',
                'currencyFormat' => 'USD',
            ],
            'settings_privacy' => [
                'presence' => 'hidden',
                'readReceipt' => false,
            ],
        ]);

        $this->assertEquals('UTC', $dto->settings_localization['timezone']);
        $this->assertEquals('DD/MM/YYYY', $dto->settings_localization['dateFormat']); // default
        $this->assertEquals('24h', $dto->settings_localization['timeFormat']);         // default
        $this->assertEquals('USD', $dto->settings_localization['currencyFormat']);
        $this->assertEquals('hidden', $dto->settings_privacy['presence']);
        $this->assertFalse($dto->settings_privacy['readReceipt']);
        $this->assertTrue($dto->settings_privacy['notificationPreview']); // default
    }

    public function test_from_request_behaves_same_as_from_array(): void
    {
        $payload = [
            'settings_localization' => ['timeFormat' => '12h'],
            'settings_privacy' => ['notificationPreview' => false],
        ];

        $fromArray = TenantSettingDTO::fromArray($payload);
        $fromRequest = TenantSettingDTO::fromRequest($payload);

        $this->assertEquals($fromArray->toArray(), $fromRequest->toArray());
    }

    public function test_to_array_returns_complete_structure(): void
    {
        $dto = TenantSettingDTO::fromArray([
            'settings_localization' => ['timezone' => 'Europe/London'],
        ]);

        $array = $dto->toArray();

        $this->assertArrayHasKey('settings_localization', $array);
        $this->assertArrayHasKey('settings_privacy', $array);
        $this->assertEquals('Europe/London', $array['settings_localization']['timezone']);
    }
}
