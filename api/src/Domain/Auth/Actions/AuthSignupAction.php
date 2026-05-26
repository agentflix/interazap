<?php

declare(strict_types=1);

namespace Domain\Auth\Actions;

use Domain\Auth\DTOs\AuthAuthenticatedUserDTO;
use Domain\Auth\DTOs\AuthSessionDTO;
use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Billing\Services\BillingGatewayService;
use Domain\Platform\Actions\PlatformTenantBootstrapAction;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Action de signup público — cria tenant + user + plano trial em transação única.
 *
 * Etapas:
 * 1. Verifica que não existe user com o email
 * 2. Resolve plano trial ativo (is_trial=true, is_active=true)
 * 3. Cria tenant via PlatformTenantBootstrapAction
 * 4. Cria AuthUser com role Inquilino
 * 5. Tenta criar customer no Asaas (não-bloqueante — falha logada, não relançada)
 * 6. Emite Sanctum token e retorna AuthSessionDTO
 *
 * @throws ValidationException se email duplicado ou plano trial indisponível
 */
final class AuthSignupAction
{
    public function __construct(
        private readonly PlatformTenantBootstrapAction $bootstrapAction,
        private readonly BillingGatewayService $billingGatewayService,
    ) {}

    /**
     * @param  array{name: string, email: string, password: string|null, email_verified_at?: string|null, provider?: string|null, provider_id?: string|null}  $payload
     *
     * @throws ValidationException
     */
    public function execute(array $payload): AuthSessionDTO
    {
        $email = strtolower(trim($payload['email']));

        $trialPlan = PlatformPlan::query()
            ->where('is_trial', true)
            ->where('is_active', true)
            ->first();

        if (! $trialPlan) {
            throw ValidationException::withMessages([
                'plan' => ['Plano trial indisponível no momento. Tente novamente mais tarde.'],
            ]);
        }

        $session = DB::transaction(function () use ($payload, $email, $trialPlan): AuthSessionDTO {
            // Create tenant
            $tenant = new PlatformTenant;
            $tenant->id = (string) Str::orderedUuid();
            $tenant->name = $payload['name'];
            $tenant->tenant_code = strtoupper(Str::random(8));
            $tenant->primary_email = $email;
            $tenant->plan_id = $trialPlan->id;
            $tenant->is_active = true;
            $tenant->billing_webhook_token = (string) Str::uuid();
            $tenant->save();

            // Bootstrap tenant data (agents, funnels, etc.)
            $this->bootstrapAction->execute($tenant);

            // Create owner user with duplicate check inside transaction
            try {
                $user = new AuthUser;
                $user->id = (string) Str::orderedUuid();
                $user->tenant_id = $tenant->id;
                $user->name = $payload['name'];
                $user->email = $email;
                $user->password = $payload['password'] ? Hash::make($payload['password']) : null;
                $user->email_verified_at = $payload['email_verified_at'] ?? null;
                $user->is_active = true;

                if (isset($payload['provider']) && $payload['provider']) {
                    $user->provider = $payload['provider'];
                    $user->provider_id = $payload['provider_id'] ?? null;
                }

                $user->save();
            } catch (\Illuminate\Database\QueryException $e) {
                if (str_contains($e->getMessage(), 'auth_users_email_unique')) {
                    throw ValidationException::withMessages([
                        'email' => ['Este email já está cadastrado.'],
                    ]);
                }
                throw $e;
            }

            // Assign tenant owner role
            $ownerRole = AuthRole::query()->where('id', AuthRole::INQUILINO_ID)->firstOrFail();
            $user->assignRole($ownerRole);

            // Refresh to load relations
            $user->refresh();

            // Emit Sanctum token
            $token = $user->createToken('signup')->plainTextToken;

            // Build permissions list
            $permissions = $user->getAllPermissions()->pluck('name')->toArray();

            $tenantPlan = [
                'id' => $trialPlan->id,
                'name' => $trialPlan->name,
                'slug' => $trialPlan->slug,
                'ai_enabled' => (bool) $trialPlan->ai_enabled,
                'is_trial' => true,
            ];

            Log::info('auth.signup.success', [
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'plan_id' => $trialPlan->id,
                'provider' => $payload['provider'] ?? 'email',
            ]);

            return new AuthSessionDTO(
                user: AuthAuthenticatedUserDTO::fromModel($user),
                permissions: $permissions,
                tenantPlan: $tenantPlan,
                token: $token,
            );
        });

        // Try to create Asaas customer — non-blocking
        $this->tryEnsureAsaasCustomer($session->user->id);

        return $session;
    }

    /**
     * Tenta criar o cliente no Asaas de forma nao-bloqueante.
     *
     * Falhas são logadas mas não interrompem o fluxo de signup.
     *
     * @param  string  $userId  UUID do usuário recém-criado.
     */
    private function tryEnsureAsaasCustomer(string $userId): void
    {
        try {
            $user = AuthUser::query()->find($userId);
            if (! $user) {
                return;
            }

            $tenant = PlatformTenant::query()->find($user->tenant_id);
            if (! $tenant) {
                return;
            }

            $this->billingGatewayService->ensureCustomer($tenant);
        } catch (\Throwable $e) {
            Log::warning('auth.signup.asaas_customer_failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
