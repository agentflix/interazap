<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\Auth\Models\AuthRole;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Performance seed for Auth context.
 *
 * Creates users, device tokens, and personal access tokens per tenant.
 */
final class PerformanceAuthSeeder
{
    use WithoutModelEvents;

    /** Batch size for inserts. */
    private const int BATCH_SIZE = 500;

    public function seedForTenant(string $tenantId): void
    {
        $userIds = $this->seedUsers($tenantId);
        $this->seedDeviceTokens($tenantId, $userIds);
        $this->seedPersonalAccessTokens($userIds);
    }

    /**
     * Create 10-15 users per tenant with varied roles and statuses.
     * Guarantees at least 1 active admin per tenant.
     *
     * @return array<int, string> User IDs
     */
    private function seedUsers(string $tenantId): array
    {
        $faker = fake('pt_BR');
        $roleWeights = [AuthRole::ADMINISTRADOR_NAME => 10, AuthRole::GERENTE_NAME => 20, AuthRole::ATENDENTE_NAME => 70];

        $users = [];
        $userIds = [];
        $count = random_int(10, 15);

        for ($i = 0; $i < $count; $i++) {
            // Guarantee first user is an active admin
            if ($i === 0) {
                $roleName = AuthRole::ADMINISTRADOR_NAME;
                $isActive = true;
            } else {
                $roleName = PerformanceSeeder::weightedRandom($roleWeights);
                $isActive = random_int(0, 100) > 10; // 90% active
            }
            $isDeleted = $i === 0 ? false : (random_int(0, 100) > 95); // 5% soft-deleted (not first user)

            $userId = PerformanceSeeder::uuid();
            $userIds[] = $userId;

            $users[] = [
                'id' => $userId,
                'tenant_id' => $tenantId,
                'name' => $faker->name(),
                'email' => "user{$i}.{$tenantId}@perf.local",
                'phone' => '+55'.random_int(1100000000, 99999999999),
                'avatar_url' => random_int(0, 1) !== 0 ? "https://avatars.perf.local/{$userId}.png" : null,
                'email_verified_at' => $isActive ? PerformanceSeeder::randomDate() : null,
                'password' => Hash::make('password'),
                'remember_token' => Str::random(10),
                'is_active' => $isActive,
                'two_factor_enabled' => (bool) random_int(0, 1),
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'preferences' => json_encode(['theme' => ['light', 'dark'][array_rand(['light', 'dark'])]]),
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
                'deleted_at' => $isDeleted ? now()->subDays(random_int(1, 30)) : null,
            ];
        }

        PerformanceSeeder::insertBatch('auth_users', $users, self::BATCH_SIZE);

        // Assign roles via pivot table (first user guaranteed admin)
        $this->assignRoles($userIds, $roleWeights);

        return $userIds;
    }

    /**
     * Assign roles to users via auth_model_has_roles pivot.
     * First user is guaranteed admin role.
     *
     * @param  array<int, string>  $userIds
     * @param  array<string, int>  $roleWeights
     */
    private function assignRoles(array $userIds, array $roleWeights): void
    {
        $roleMap = [
            AuthRole::ADMINISTRADOR_NAME => AuthRole::ADMINISTRADOR_ID,
            AuthRole::GERENTE_NAME => AuthRole::GERENTE_ID,
            AuthRole::ATENDENTE_NAME => AuthRole::ATENDENTE_ID,
        ];

        $pivot = [];
        foreach ($userIds as $index => $userId) {
            // First user guaranteed admin
            if ($index === 0) {
                $roleId = AuthRole::ADMINISTRADOR_ID;
            } else {
                $roleName = PerformanceSeeder::weightedRandom($roleWeights);
                $roleId = $roleMap[$roleName] ?? AuthRole::ATENDENTE_ID;
            }

            $pivot[] = [
                'role_id' => $roleId,
                'model_type' => \Domain\Auth\Models\AuthUser::class,
                'model_id' => $userId,
            ];
        }

        PerformanceSeeder::insertBatch('auth_model_has_roles', $pivot, self::BATCH_SIZE);
    }

    /**
     * Create device tokens for random subset of users.
     *
     * @param  array<int, string>  $userIds
     */
    private function seedDeviceTokens(string $tenantId, array $userIds): void
    {
        $platforms = ['ios', 'android', 'web'];
        $tokens = [];

        // Create tokens for ~60% of users, 1-2 tokens each
        foreach ($userIds as $userId) {
            if (random_int(0, 100) > 40) {
                $tokenCount = random_int(1, 2);
                for ($t = 0; $t < $tokenCount; $t++) {
                    $isRevoked = random_int(0, 100) > 90; // 10% revoked

                    $tokens[] = [
                        'id' => PerformanceSeeder::uuid(),
                        'tenant_id' => $tenantId,
                        'user_id' => $userId,
                        'platform' => $platforms[array_rand($platforms)],
                        'token' => base64_encode(random_bytes(128)),
                        'device_name' => 'Device '.random_int(100, 999),
                        'last_active_at' => PerformanceSeeder::randomDate(),
                        'revoked_at' => $isRevoked ? now()->subDays(random_int(1, 30)) : null,
                        'created_at' => PerformanceSeeder::randomDate(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        if ($tokens !== []) {
            PerformanceSeeder::insertBatch('auth_device_tokens', $tokens, self::BATCH_SIZE);
        }
    }

    /**
     * Create personal access tokens for random subset of users.
     *
     * @param  array<int, string>  $userIds
     */
    private function seedPersonalAccessTokens(array $userIds): void
    {
        $tokens = [];

        // Create PATs for ~30% of users
        foreach ($userIds as $userId) {
            if (random_int(0, 100) > 70) {
                $hasExpiry = (bool) random_int(0, 1);
                $wasUsed = (bool) random_int(0, 1);

                $tokens[] = [
                    'id' => PerformanceSeeder::uuid(),
                    'tokenable_type' => \Domain\Auth\Models\AuthUser::class,
                    'tokenable_id' => $userId,
                    'name' => 'Token '.random_int(100, 999),
                    'token' => hash('sha256', random_bytes(32)),
                    'abilities' => json_encode(['*']),
                    'last_used_at' => $wasUsed ? PerformanceSeeder::randomDate() : null,
                    'expires_at' => $hasExpiry ? now()->addDays(random_int(1, 365)) : null,
                    'created_at' => PerformanceSeeder::randomDate(),
                    'updated_at' => now(),
                ];
            }
        }

        if ($tokens !== []) {
            PerformanceSeeder::insertBatch('auth_personal_access_tokens', $tokens, self::BATCH_SIZE);
        }
    }
}
