<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use Domain\Auth\Models\AuthUser;
use Domain\Auth\Services\AuthRecoveryCodeService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class AuthRecoveryCodeServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_validate_returns_true_for_existing_code(): void
    {
        $user = AuthUser::factory()->create([
            'two_factor_recovery_codes' => json_encode(['ABC123', 'XYZ999'], JSON_THROW_ON_ERROR),
        ]);

        $service = new AuthRecoveryCodeService;

        $this->assertTrue($service->validate('ABC123', $user));
    }

    public function test_invalidate_removes_code_from_user(): void
    {
        $user = AuthUser::factory()->create([
            'two_factor_recovery_codes' => json_encode(['ABC123', 'XYZ999'], JSON_THROW_ON_ERROR),
        ]);

        $service = new AuthRecoveryCodeService;
        $service->invalidate('ABC123', $user);

        $remaining = json_decode((string) $user->two_factor_recovery_codes, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(['XYZ999'], $remaining);
    }

    public function test_validate_returns_false_when_codes_payload_is_invalid_json(): void
    {
        $user = AuthUser::factory()->create([
            'two_factor_recovery_codes' => '{invalid-json}',
        ]);

        $service = new AuthRecoveryCodeService;

        $this->assertFalse($service->validate('ABC123', $user));
    }
}
