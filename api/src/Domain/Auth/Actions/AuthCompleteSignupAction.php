<?php

declare(strict_types=1);

namespace Domain\Auth\Actions;

use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class AuthCompleteSignupAction
{
    /**
     * Completa o cadastro — coleta empresa, telefone e senha (opcional para usuários com senha).
     *
     * @param  array<string, string|null>  $data  Dados validados: company_name, phone, password (nullable)
     */
    public function execute(AuthUser $user, array $data): AuthUser
    {
        DB::transaction(function () use ($user, $data): void {
            $user->phone = $data['phone'];

            if (! empty($data['password']) && $user->password === null) {
                $user->password = Hash::make($data['password']);
            }

            $user->save();

            PlatformTenant::query()
                ->where('id', $user->tenant_id)
                ->update(['name' => $data['company_name']]);
        });

        return $user->refresh();
    }
}
