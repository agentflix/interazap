<?php

declare(strict_types=1);

use Domain\Auth\Models\AuthUser;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;

/**
 * Criptografa todos os two_factor_secret existentes.
 *
 * SECURITY: Esta migration protege secrets 2FA armazenados em plaintext,
 * prevenindo bypass de autenticação caso o banco seja comprometido.
 */
return new class extends Migration
{
    public function up(): void
    {
        AuthUser::query()
            ->whereNotNull('two_factor_secret')
            ->where('two_factor_secret', '!=', '')
            ->each(function (AuthUser $user): void {
                // Verifica se já está criptografado
                try {
                    Crypt::decryptString($user->two_factor_secret);

                    // Já está criptografado, pular
                    return;
                } catch (DecryptException) {
                    // Não está criptografado, criptografar
                }

                // Criptografa e salva sem disparar eventos/observers
                $user->timestamps = false;
                $user->two_factor_secret = Crypt::encryptString($user->two_factor_secret);
                $user->saveQuietly();
            });
    }

    /**
     * AVISO: O rollback expõe secrets em plaintext no banco.
     * Usar apenas em emergências com muito cuidado.
     */
    public function down(): void
    {
        AuthUser::query()
            ->whereNotNull('two_factor_secret')
            ->where('two_factor_secret', '!=', '')
            ->each(function (AuthUser $user): void {
                try {
                    $decrypted = Crypt::decryptString($user->two_factor_secret);

                    // Salva em plaintext sem disparar eventos
                    $user->timestamps = false;
                    $user->two_factor_secret = $decrypted;
                    $user->saveQuietly();
                } catch (DecryptException) {
                    // Já está em plaintext, pular
                }
            });
    }
};
