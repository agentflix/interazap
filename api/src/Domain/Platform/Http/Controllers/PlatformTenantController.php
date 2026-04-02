<?php

declare(strict_types=1);

namespace Domain\Platform\Http\Controllers;

use Domain\Platform\Actions\GetTenantSettingsAction;
use Domain\Platform\Actions\PlatformTenantActions;
use Domain\Platform\Actions\PlatformTenantBootstrapAction;
use Domain\Platform\Actions\UpdateTenantSettingsAction;
use Domain\Platform\DTOs\PlatformTenantDTO;
use Domain\Platform\Http\Requests\PlatformTenantStoreRequest;
use Domain\Platform\Http\Requests\PlatformTenantUpdateRequest;
use Domain\Platform\Http\Requests\UpdateTenantSettingsRequest;
use Domain\Platform\Http\Resources\PlatformTenantDetailsResource;
use Domain\Platform\Http\Resources\PlatformTenantResource;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controlador para gestao de tenants da plataforma.
 *
 * @see PlatformTenantActions
 * @see PlatformTenantBootstrapAction
 */
final class PlatformTenantController extends BaseController
{
    /**
     * @param  PlatformTenantActions  $actions  Ação de gestão de tenants.
     * @param  PlatformTenantBootstrapAction  $bootstrapAction  Ação de bootstrap inicial.
     * @param  GetTenantSettingsAction  $getTenantSettings  Ação de buscar configurações do tenant.
     * @param  UpdateTenantSettingsAction  $updateTenantSettings  Ação de atualizar configurações do tenant.
     * @param  DatabaseManager  $database  Gerenciador de banco de dados.
     */
    public function __construct(
        private readonly PlatformTenantActions $actions,
        private readonly PlatformTenantBootstrapAction $bootstrapAction,
        private readonly GetTenantSettingsAction $getTenantSettings,
        private readonly UpdateTenantSettingsAction $updateTenantSettings,
        private readonly DatabaseManager $database,
    ) {}

    /**
     * Listar tenants com filtros e paginação.
     *
     * @param  Request  $request  Requisição com filtros.
     * @return JsonResponse Lista paginada de tenants.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PlatformTenant::class);

        $filters = $this->extractFilters($request);

        $paginator = $this->actions->list($filters);
        $paginator->getCollection()->transform(fn ($item) => new PlatformTenantResource($item));

        return $this->paginated($paginator, 'Tenants listados');
    }

    /**
     * Criar novo tenant com bootstrap inicial.
     *
     * @param  PlatformTenantStoreRequest  $request  Requisição com dados do tenant.
     * @return JsonResponse Tenant criado ou erro.
     */
    public function store(PlatformTenantStoreRequest $request): JsonResponse
    {
        $this->authorize('create', PlatformTenant::class);

        try {
            $tenant = $this->database->transaction(function () use ($request): PlatformTenant {
                $createdTenant = $this->actions->create(
                    PlatformTenantDTO::fromRequest($request),
                    $request->user()
                );

                $this->bootstrapAction->execute($createdTenant);

                return $createdTenant->refresh();
            });
        } catch (\Throwable $exception) {
            report($exception);

            return $this->error('Falha ao criar tenant e aplicar configuração inicial padrão.', 422);
        }

        return $this->created(new PlatformTenantResource($tenant), 'Tenant criado com sucesso');
    }

    /**
     * Obter detalhes de um tenant.
     *
     * @param  string  $id  ID do tenant.
     * @return JsonResponse Dados do tenant.
     */
    public function show(string $id): JsonResponse
    {
        $tenant = $this->actions->find($id);
        $this->authorize('view', $tenant);

        return $this->success(new PlatformTenantResource($tenant), 'Tenant carregado');
    }

    /**
     * Retorna detalhes completos do tenant: empresa, plano contratado e recursos.
     *
     * @param  string  $id  ID do tenant.
     * @return JsonResponse Detalhes do tenant.
     */
    public function details(string $id): JsonResponse
    {
        $tenant = $this->actions->find($id);
        $this->authorize('view', $tenant);

        return $this->success(new PlatformTenantDetailsResource($tenant), 'Detalhes do tenant carregados');
    }

    /**
     * Atualizar dados do tenant.
     *
     * @param  PlatformTenantUpdateRequest  $request  Requisição com dados atualizados.
     * @param  string  $id  ID do tenant.
     * @return JsonResponse Tenant atualizado.
     */
    public function update(PlatformTenantUpdateRequest $request, string $id): JsonResponse
    {
        $tenant = $this->actions->find($id);
        $this->authorize('update', $tenant);

        $tenant = $this->actions->update($tenant, PlatformTenantDTO::fromRequest($request));

        return $this->success(new PlatformTenantResource($tenant), 'Tenant atualizado com sucesso');
    }

    /**
     * Excluir tenant (soft delete).
     *
     * @param  string  $id  ID do tenant.
     * @return JsonResponse Resposta sem conteúdo.
     */
    public function destroy(string $id): JsonResponse
    {
        $tenant = $this->actions->find($id);
        $this->authorize('delete', $tenant);

        $this->actions->delete($tenant);

        return $this->noContent();
    }

    /**
     * Restaurar tenant excluído.
     *
     * @param  string  $id  ID do tenant.
     * @return JsonResponse Tenant restaurado.
     */
    public function restore(string $id): JsonResponse
    {
        $tenant = $this->actions->restore($id);
        $this->authorize('update', $tenant);

        return $this->success(new PlatformTenantResource($tenant), 'Tenant restaurado');
    }

    /**
     * Excluir tenant permanentemente.
     *
     * @param  string  $id  ID do tenant.
     * @return JsonResponse Resposta sem conteúdo.
     */
    public function forceDelete(string $id): JsonResponse
    {
        $tenant = $this->actions->find($id, true);
        $this->authorize('delete', $tenant);

        $this->actions->forceDelete($id);

        return $this->noContent();
    }

    /**
     * Ativar/inativar tenant.
     *
     * @param  string  $id  ID do tenant.
     * @return JsonResponse Tenant com status atualizado.
     */
    public function toggleActive(string $id): JsonResponse
    {
        $tenant = $this->actions->find($id);
        $this->authorize('update', $tenant);

        $tenant = $this->actions->toggleActive($id);

        return $this->success(new PlatformTenantResource($tenant), 'Status atualizado');
    }

    /**
     * Obter configurações do tenant.
     *
     * @param  string  $id  ID do tenant.
     * @return JsonResponse Configurações do tenant.
     */
    public function settings(string $id): JsonResponse
    {
        $tenant = $this->actions->find($id);
        $this->authorize('updateSettings', $tenant);

        $settings = $this->getTenantSettings->execute($tenant);

        return $this->success($settings->toArray(), 'Configurações carregadas');
    }

    /**
     * Atualizar configurações do tenant (deep merge parcial).
     *
     * @param  UpdateTenantSettingsRequest  $request  Request com dados validados.
     * @param  string  $id  ID do tenant.
     * @return JsonResponse Configurações atualizadas completas.
     */
    public function updateSettings(UpdateTenantSettingsRequest $request, string $id): JsonResponse
    {
        $tenant = $this->actions->find($id);
        $this->authorize('updateSettings', $tenant);

        $updated = $this->updateTenantSettings->execute($tenant, $request->validated());

        return $this->success($updated->toArray(), 'Configurações atualizadas');
    }

    /**
     * Exportar tenants para CSV.
     *
     * @param  Request  $request  Requisição com filtros.
     * @return StreamedResponse Arquivo CSV para download.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', PlatformTenant::class);

        $filters = $this->extractFilters($request);
        $delimiter = ';';
        $filename = sprintf('tenants_export_%s.csv', now()->format('Ymd'));
        $query = $this->actions->queryForExport($filters);

        $callback = static function () use ($query, $delimiter): void {
            $stream = fopen('php://output', 'wb');
            if ($stream === false) {
                return;
            }

            fwrite($stream, "\xEF\xBB\xBF");

            fputcsv($stream, [
                'Nome do Tenant',
                'CNPJ',
                'Telefone',
                'Email',
                'Cidade/UF',
                'Data de Cadastro',
                'Status',
            ], $delimiter);

            foreach ($query->cursor() as $tenant) {
                $city = (string) ($tenant->city ?? '');
                $state = (string) ($tenant->state ?? '');
                $cityUf = trim($city.($city !== '' && $state !== '' ? '/' : '').$state);

                $status = $tenant->deleted_at !== null
                    ? 'Excluído'
                    : ((bool) $tenant->is_active ? 'Ativo' : 'Inativo');

                fputcsv($stream, [
                    self::sanitizeCsvCell((string) $tenant->name),
                    self::sanitizeCsvCell((string) ($tenant->document ?? '')),
                    self::sanitizeCsvCell((string) ($tenant->phone ?? '')),
                    self::sanitizeCsvCell((string) ($tenant->primary_email ?? '')),
                    self::sanitizeCsvCell($cityUf),
                    self::sanitizeCsvCell($tenant->created_at?->format('d/m/Y H:i:s') ?? ''),
                    self::sanitizeCsvCell($status),
                ], $delimiter);
            }

            fclose($stream);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractFilters(Request $request): array
    {
        $filters = $request->only([
            'search',
            'is_active',
            'trashed',
            'per_page',
            'page',
            'sort_by',
            'sort_dir',
            'created_from',
            'created_to',
        ]);

        if ($request->has('is_active')) {
            $filters['is_active'] = $request->boolean('is_active');
        }

        if ($request->has('trashed')) {
            $filters['trashed'] = $request->boolean('trashed');
        }

        return $filters;
    }

    private static function sanitizeCsvCell(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        $firstChar = $value[0];
        if (in_array($firstChar, ['=', '+', '-', '@'], true)) {
            return "'".$value;
        }

        return $value;
    }
}
