<?php

declare(strict_types=1);

namespace Domain\Auth\Services;

use Domain\Auth\Models\AuthUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Gerenciador de avatares de usuários.
 *
 * Responsável pela persistência, upload e remoção segura de imagens
 * de perfil dos usuários, gerenciando o armazenamento em disco.
 *
 * @category Services
 */
final class AuthAvatarManager
{
    /**
     * Atualizar a imagem de perfil do usuário.
     *
     * Processa o upload da nova imagem, salva no disco público, remove
     * o avatar anterior (se existir) e atualiza o registro do usuário.
     *
     * @param  AuthUser  $user  O usuário que está atualizando o perfil.
     * @param  UploadedFile  $image  O arquivo da nova imagem enviada.
     * @return array{avatar_url:string} Retorna a URL pública da nova imagem.
     *
     * @throws ValidationException Caso ocorra erro ao salvar o arquivo no disco.
     */
    public function update(AuthUser $user, UploadedFile $image): array
    {
        $publicDisk = Storage::disk('public');
        $directory = sprintf('profiles/%s', $user->id);

        $extension = $image->guessExtension() ?: $image->getClientOriginalExtension() ?: 'jpg';
        $fileName = sprintf('avatar-%s.%s', now()->format('YmdHis'), $extension);

        $storedPath = $publicDisk->putFileAs($directory, $image, $fileName);

        if (! $storedPath) {
            throw ValidationException::withMessages([
                'image' => ['Falha ao salvar a imagem de perfil.'],
            ]);
        }

        $this->deleteExistingAvatar($user->avatar_url);

        $diskUrl = $publicDisk->url($storedPath);
        $avatarUrl = url($diskUrl);

        $user->avatar_url = $avatarUrl;
        $user->save();

        return [
            'avatar_url' => $avatarUrl,
        ];
    }

    /**
     * Remover a imagem de perfil atual.
     *
     * Exclui o arquivo físico do disco e limpa a referência no banco de dados,
     * retornando o usuário ao estado "sem avatar".
     *
     * @param  AuthUser  $user  O usuário que terá o avatar removido.
     * @return array{avatar_url:null} Retorna null indicando remoção.
     */
    public function remove(AuthUser $user): array
    {
        $this->deleteExistingAvatar($user->avatar_url);

        $user->avatar_url = null;
        $user->save();

        return [
            'avatar_url' => null,
        ];
    }

    /**
     * Excluir o arquivo de avatar do disco.
     *
     * Analisa a URL para identificar o caminho relativo e remove o arquivo
     * se ele estiver armazenado localmente.
     *
     * @param  string|null  $avatarUrl  URL completa do avatar a ser removido.
     */
    private function deleteExistingAvatar(?string $avatarUrl): void
    {
        if (! $avatarUrl) {
            return;
        }

        $path = parse_url($avatarUrl, PHP_URL_PATH);

        if (! $path || ! str_contains($path, '/storage/')) {
            return;
        }

        $relativePath = ltrim(substr($path, strpos($path, '/storage/') + strlen('/storage/')), '/');

        if ($relativePath !== '') {
            Storage::disk('public')->delete($relativePath);
        }
    }
}
