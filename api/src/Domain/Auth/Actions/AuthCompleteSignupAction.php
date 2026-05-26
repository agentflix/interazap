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
     * Completa o cadastro de usuário criado via Google OAuth.
     *
     * Define senha, telefone e nome da empresa em uma transação.
     *
     * @param  array<string, string>  $data  Dados validados: company_name, phone, password
     */
    public function execute(AuthUser $user, array $data): AuthUser
    {
        DB::transaction(function () use ($user, $data): void {
            $user->phone    = $data['phone'];
            $user->password = Hash::make($data['password']);
            $user->save();

            PlatformTenant::query()
                ->where('id', $user->tenant_id)
                ->update(['name' => $data['company_name']]);
        });

        return $user->refresh();
    }
}
