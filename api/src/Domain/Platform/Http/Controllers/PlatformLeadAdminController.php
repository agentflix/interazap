<?php

declare(strict_types=1);

namespace Domain\Platform\Http\Controllers;

use Domain\Platform\Actions\PlatformLeadAdminActions;
use Domain\Platform\Http\Requests\PlatformLeadConvertRequest;
use Domain\Platform\Http\Resources\PlatformLeadAdminResource;
use Domain\Platform\Models\PlatformLead;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controlador administrativo para leads da plataforma.
 */
final class PlatformLeadAdminController extends BaseController
{
    public function __construct(
        private readonly PlatformLeadAdminActions $actions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PlatformLead::class);

        $filters = $request->only([
            'search',
            'page',
            'per_page',
            'sort_by',
            'sort_dir',
        ]);

        $paginator = $this->actions->list($filters);
        $paginator->getCollection()->transform(fn ($item) => new PlatformLeadAdminResource($item));

        return $this->paginated($paginator, 'Leads listados');
    }

    public function convert(PlatformLeadConvertRequest $request, string $id): JsonResponse
    {
        $lead = PlatformLead::query()->findOrFail($id);
        $this->authorize('create', PlatformLead::class);

        try {
            $converted = $this->actions->convert($lead, $request->validated());
        } catch (\Throwable $exception) {
            report($exception);

            return $this->error('Falha ao converter lead em tenant.', 422);
        }

        return $this->success(new PlatformLeadAdminResource($converted), 'Lead convertido com sucesso');
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', PlatformLead::class);

        $filters = $request->only([
            'search',
            'sort_by',
            'sort_dir',
        ]);

        $delimiter = ';';
        $filename = sprintf('platform_leads_%s.csv', now()->format('Y-m-d'));
        $query = $this->actions->queryForExport($filters);

        $callback = static function () use ($query, $delimiter): void {
            $stream = fopen('php://output', 'wb');
            if ($stream === false) {
                return;
            }

            fwrite($stream, "\xEF\xBB\xBF");

            fputcsv($stream, [
                'ID',
                'Nome',
                'Email',
                'Telefone',
                'Empresa',
                'Consentimento LGPD',
                'Data de Cadastro',
                'UTM Source',
                'UTM Campaign',
            ], $delimiter);

            foreach ($query->cursor() as $lead) {
                fputcsv($stream, [
                    self::sanitizeCsvCell((string) $lead->id),
                    self::sanitizeCsvCell((string) $lead->name),
                    self::sanitizeCsvCell((string) $lead->email),
                    self::sanitizeCsvCell((string) $lead->phone),
                    self::sanitizeCsvCell((string) ($lead->company ?? '')),
                    self::sanitizeCsvCell((bool) $lead->lgpd_consent ? 'Sim' : 'Não'),
                    self::sanitizeCsvCell($lead->created_at?->format('d/m/Y') ?? ''),
                    self::sanitizeCsvCell((string) ($lead->utm_source ?? '')),
                    self::sanitizeCsvCell((string) ($lead->utm_campaign ?? '')),
                ], $delimiter);
            }

            fclose($stream);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
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
