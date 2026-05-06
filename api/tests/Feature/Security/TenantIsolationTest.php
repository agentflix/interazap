<?php

declare(strict_types=1);

use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

it('prevents cross-tenant access to users', function (): void {
    $victim = AuthUser::factory()->create();
    $attacker = AuthUser::factory()->create();

    AuthRole::query()->firstOrCreate([
        'id' => AuthRole::INQUILINO_ID,
        'name' => AuthRole::INQUILINO_NAME,
        'guard_name' => 'sanctum',
    ]);

    $attacker->assignRole(AuthRole::INQUILINO_ID);

    actingAs($attacker, 'sanctum');

    getJson("/api/auth/users/{$victim->id}")
        ->assertNotFound();
});
