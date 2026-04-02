<?php

declare(strict_types=1);

namespace Tests\Unit;

use Domain\Auth\DTOs\UserPreferenceDTO;
use PHPUnit\Framework\TestCase;

class UserPreferenceDTOTest extends TestCase
{
    public function test_from_array_creates_dto_with_all_defaults(): void
    {
        $dto = UserPreferenceDTO::fromArray([]);

        $this->assertEquals('system', $dto->appearance['theme']);
        $this->assertEquals('normal', $dto->appearance['density']);
        $this->assertEquals('medium', $dto->appearance['fontSize']);
        $this->assertTrue($dto->behavior['sound']);
        $this->assertTrue($dto->behavior['chatNotify']);
        $this->assertFalse($dto->behavior['quickReply']);
        $this->assertTrue($dto->behavior['confirmBulk']);
        $this->assertEquals('modal', $dto->behavior['ticketOpenMode']);
        $this->assertEquals('basic', $dto->crmDefaults['negotiationType']);
        $this->assertEquals('pending', $dto->crmDefaults['taskStatus']);
        $this->assertEquals('kanban', $dto->crmDefaults['pipelineView']);
        $this->assertEquals('date', $dto->crmDefaults['negotiationOrder']);
        $this->assertEquals(60, $dto->security['sessionTimeout']);
        $this->assertFalse($dto->accessibility['highContrast']);
        $this->assertFalse($dto->accessibility['reducedMotion']);
    }

    public function test_from_array_applies_partial_overrides(): void
    {
        $dto = UserPreferenceDTO::fromArray([
            'appearance' => ['theme' => 'dark', 'density' => 'compact'],
            'behavior' => ['sound' => false],
            'crmDefaults' => ['taskStatus' => 'done'],
            'accessibility' => ['highContrast' => true],
        ]);

        $this->assertEquals('dark', $dto->appearance['theme']);
        $this->assertEquals('compact', $dto->appearance['density']);
        $this->assertEquals('medium', $dto->appearance['fontSize']); // default
        $this->assertFalse($dto->behavior['sound']);
        $this->assertTrue($dto->behavior['chatNotify']); // default
        $this->assertEquals('done', $dto->crmDefaults['taskStatus']);
        $this->assertTrue($dto->accessibility['highContrast']);
        $this->assertFalse($dto->accessibility['reducedMotion']); // default
    }

    public function test_from_request_behaves_same_as_from_array(): void
    {
        $payload = [
            'appearance' => ['theme' => 'light'],
            'behavior' => ['quickReply' => true],
            'crmDefaults' => ['negotiationType' => 'advanced'],
        ];

        $fromArray = UserPreferenceDTO::fromArray($payload);
        $fromRequest = UserPreferenceDTO::fromRequest($payload);

        $this->assertEquals($fromArray->toArray(), $fromRequest->toArray());
    }

    public function test_to_array_returns_complete_structure(): void
    {
        $dto = UserPreferenceDTO::fromArray([
            'appearance' => ['theme' => 'dark'],
        ]);

        $array = $dto->toArray();

        $this->assertArrayHasKey('appearance', $array);
        $this->assertArrayHasKey('behavior', $array);
        $this->assertArrayHasKey('crmDefaults', $array);
        $this->assertArrayHasKey('security', $array);
        $this->assertArrayHasKey('accessibility', $array);
        $this->assertEquals('dark', $array['appearance']['theme']);
    }

    public function test_defaults_are_always_applied(): void
    {
        $dto = UserPreferenceDTO::fromArray([
            'appearance' => ['theme' => 'dark', 'density' => 'expanded'],
        ]);

        // density was overridden, but fontSize should use default
        $this->assertEquals('dark', $dto->appearance['theme']);
        $this->assertEquals('expanded', $dto->appearance['density']);
        $this->assertEquals('medium', $dto->appearance['fontSize']);

        // behavior section was not provided at all, should use all defaults
        $this->assertTrue($dto->behavior['sound']);
        $this->assertEquals('modal', $dto->behavior['ticketOpenMode']);
    }
}
