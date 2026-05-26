<?php

declare(strict_types=1);

namespace Domain\Auth\Http\Controllers;

use Domain\Auth\Actions\AuthCompleteSignupAction;
use Domain\Auth\Actions\AuthProfileActions;
use Domain\Auth\Actions\GetUserPreferencesAction;
use Domain\Auth\Actions\UpdateUserPreferencesAction;
use Domain\Auth\DTOs\AuthProfileDTO;
use Domain\Auth\DTOs\AuthUpdatePasswordDTO;
use Domain\Auth\Http\Requests\AuthCompleteSignupRequest;
use Domain\Auth\Http\Requests\AuthForcePasswordChangeRequest;
use Domain\Auth\Http\Requests\AuthPasswordUpdateRequest;
use Domain\Auth\Http\Requests\AuthProfileImageRequest;
use Domain\Auth\Http\Requests\AuthProfileUpdateRequest;
use Domain\Auth\Http\Requests\UpdateUserPreferencesRequest;
use Domain\Auth\Http\Resources\AuthUserResource;
use Domain\Auth\Models\AuthUser;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Endpoints para perfil do usuário autenticado.
 *
 * @see AuthProfileActions
 */
final class AuthProfileController extends BaseController
{
    /**
     * @param  AuthProfileActions  $profileActions  Ação de perfil do usuário.
     * @param  GetUserPreferencesAction  $getUserPreferences  Ação de buscar preferências.
     * @param  UpdateUserPreferencesAction  $updateUserPreferences  Ação de atualizar preferências.
     */
    public function __construct(
        private readonly AuthProfileActions $profileActions,
        private readonly GetUserPreferencesAction $getUserPreferences,
        private readonly UpdateUserPreferencesAction $updateUserPreferences,
        private readonly AuthCompleteSignupAction $completeSignupAction,
    ) {}

    /**
     * Completa cadastro de usuário criado via Google OAuth.
     * Define senha, telefone e nome da empresa.
     */
    public function completeSignup(AuthCompleteSignupRequest $request): JsonResponse
    {
        /** @var AuthUser $user */
        $user = $request->user();
        $updated = $this->completeSignupAction->execute($user, $request->validated());

        return $this->success(new AuthUserResource($updated), 'Cadastro concluído');
    }

    /**
     * Obter dados do perfil do usuário autenticado.
     *
     * @param  Request  $request  Requisição atual.
     * @return JsonResponse Dados do perfil.
     */
    public function show(Request $request): JsonResponse
    {
        /** @var AuthUser $user */
        $user = $request->user();

        return $this->success(new AuthUserResource($this->profileActions->get($user)), 'Perfil carregado');
    }

    /**
     * Atualizar dados do perfil.
     *
     * @param  AuthProfileUpdateRequest  $request  Requisição com dados do perfil.
     * @return JsonResponse Perfil atualizado.
     */
    public function update(AuthProfileUpdateRequest $request): JsonResponse
    {
        /** @var AuthUser $user */
        $user = $request->user();
        $updated = $this->profileActions->update($user, AuthProfileDTO::fromRequest($request));

        return $this->success(new AuthUserResource($updated), 'Perfil atualizado');
    }

    /**
     * Atualizar senha do usuário.
     *
     * @param  AuthPasswordUpdateRequest  $request  Requisição com senha atual e nova senha.
     * @return JsonResponse Confirmação.
     */
    public function updatePassword(AuthPasswordUpdateRequest $request): JsonResponse
    {
        /** @var AuthUser $user */
        $user = $request->user();
        $this->profileActions->updatePassword($user, AuthUpdatePasswordDTO::fromRequest($request));

        return $this->success(null, 'Senha atualizada com sucesso');
    }

    /**
     * Forçar troca de senha sem exigir senha atual.
     *
     * @param  AuthForcePasswordChangeRequest  $request  Requisição com nova senha.
     * @return JsonResponse Confirmação.
     */
    public function forcePasswordChange(AuthForcePasswordChangeRequest $request): JsonResponse
    {
        /** @var AuthUser $user */
        $user = $request->user();

        /** @var string $password */
        $password = $request->input('password');
        $this->profileActions->forcePasswordChange($user, $password);

        return $this->success(null, 'Senha redefinida com sucesso');
    }

    /**
     * Upload de avatar do usuário.
     *
     * @param  AuthProfileImageRequest  $request  Requisição com arquivo de imagem.
     * @return JsonResponse Avatar atualizado.
     */
    public function uploadAvatar(AuthProfileImageRequest $request): JsonResponse
    {
        /** @var AuthUser $user */
        $user = $request->user();
        $image = $request->file('image') ?? $request->file('avatar');

        if (! $image instanceof UploadedFile) {
            return $this->error('Arquivo de imagem é obrigatório.', 422);
        }

        $payload = $this->profileActions->updateAvatar($user, $image);

        return $this->success($payload, 'Avatar atualizado');
    }

    /**
     * Remover avatar do usuário.
     *
     * @param  Request  $request  Requisição atual.
     * @return JsonResponse Confirmação.
     */
    public function deleteAvatar(Request $request): JsonResponse
    {
        /** @var AuthUser $user */
        $user = $request->user();
        $payload = $this->profileActions->deleteAvatar($user);

        return $this->success($payload, 'Avatar removido');
    }

    /**
     * Obter preferências do usuário autenticado.
     *
     * @param  Request  $request  Requisição atual.
     * @return JsonResponse Preferências completas com defaults aplicados.
     */
    public function preferences(Request $request): JsonResponse
    {
        /** @var AuthUser $user */
        $user = $request->user();
        $this->authorize('updatePreferences', $user);

        $prefs = $this->getUserPreferences->execute($user);

        return $this->success($prefs->toArray(), 'Preferências carregadas');
    }

    /**
     * Atualizar preferências do usuário autenticado (deep merge parcial).
     *
     * @param  UpdateUserPreferencesRequest  $request  Request com dados validados.
     * @return JsonResponse Preferências atualizadas completas.
     */
    public function updatePreferences(UpdateUserPreferencesRequest $request): JsonResponse
    {
        /** @var AuthUser $user */
        $user = $request->user();
        $this->authorize('updatePreferences', $user);

        $updated = $this->updateUserPreferences->execute($user, $request->validated());

        return $this->success($updated->toArray(), 'Preferências atualizadas');
    }
}
