<?php

declare(strict_types=1);

namespace Tests\Unit\Chat\Enums;

use Domain\Chat\Enums\ProviderType;
use Tests\TestCase;

final class ProviderTypeWebTest extends TestCase
{
    public function test_provider_type_we_b_has_value_web(): void
    {
        $this->assertEquals('web', ProviderType::WEB->value);
    }

    public function test_provider_type_we_b_has_correct_label(): void
    {
        $this->assertEquals('Webchat', ProviderType::WEB->label());
    }

    public function test_all_provider_type_cases_are_available(): void
    {
        $cases = ProviderType::cases();

        $this->assertCount(4, $cases);
        $this->assertContains(ProviderType::UAZAPI, $cases);
        $this->assertContains(ProviderType::ZAPI, $cases);
        $this->assertContains(ProviderType::META, $cases);
        $this->assertContains(ProviderType::WEB, $cases);
    }
}
