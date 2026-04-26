<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Controllers;

use Domain\CRM\Actions\CRMContactImportActions;
use Domain\CRM\DTOs\CRMContactImportDTO;
use Domain\CRM\Http\Requests\CRMContactExportRequest;
use Domain\CRM\Http\Requests\CRMContactImportRequest;
use Domain\CRM\Http\Requests\CRMContactImportUploadRequest;
use Domain\CRM\Jobs\CRMContactImportJob;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Services\CRMCsvParsingService;
use Domain\Shared\Http\Controllers\BaseController;
use Domain\Shared\Support\SearchSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Csv\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Importação e exportação simples de contatos via CSV.
 */
final class CRMContactImportExportController extends BaseController
{
    public function __construct(
        private readonly CRMContactImportActions $importActions,
        private readonly CRMCsvParsingService $csvService,
    ) {}

    /**
     * Upload a CSV file and return headers + sample rows for mapping.
     */
    public function upload(CRMContactImportUploadRequest $request): JsonResponse
    {
        $this->authorize('create', CRMContact::class);

        $tenantId = $this->tenantId($request);
        $file = $request->file('file');
        $storagePath = (string) config('crm.contact_import.storage_path', 'imports/contacts');
        $tenantPath = $storagePath.'/'.$tenantId;
        $path = $file->storeAs($tenantPath, Str::orderedUuid().'.csv');
        if ($path === false) {
            return $this->error('Não foi possível salvar o arquivo.', 400);
        }

        $absolute = Storage::path($path);
        $delimiter = $this->csvService->detectDelimiter($absolute);

        $sampleRows = (int) config('crm.contact_import.sample_rows', 5);

        try {
            $preview = $this->csvService->getPreview($absolute, $delimiter, $sampleRows);
        } catch (\Throwable $exception) {
            report($exception);

            return $this->error('Não foi possível processar o arquivo.', 400);
        }

        return $this->success([
            'path' => $path,
            'import_id' => $path,
            'headers' => $preview['header'],
            'sample' => $preview['sample'],
            'delimiter' => $delimiter,
            'has_header' => true,
            'rows' => count($preview['sample']),
        ], 'Arquivo recebido.');
    }

    /**
     * Import contacts from a previously uploaded CSV file.
     */
    public function import(CRMContactImportRequest $request): JsonResponse
    {
        $this->authorize('create', CRMContact::class);

        $dto = CRMContactImportDTO::fromRequest($request);
        $totalRows = $this->csvService->countRows($dto->filePath, $dto->delimiter, $dto->hasHeader);

        $inlineMaxRows = (int) config('crm.contact_import.inline_max_rows', 1000);
        if ($totalRows > $inlineMaxRows) {
            CRMContactImportJob::dispatch($dto->toArray());

            return $this->success([
                'queued' => true,
                'rows' => $totalRows,
            ], 'Importação enfileirada. Você receberá o resumo ao final.');
        }

        $summary = $this->importActions->process($dto);

        return $this->success($summary, 'Importação concluída.');
    }

    /**
     * Export contacts as a streamed CSV download.
     */
    public function export(CRMContactExportRequest $request): StreamedResponse
    {
        $this->authorize('viewAny', CRMContact::class);

        $tenantId = $this->tenantId($request);
        $search = $request->validated('search');

        $query = CRMContact::query()
            ->with('company')
            ->where('tenant_id', $tenantId)
            ->orderBy('name');

        if ($search !== null && $search !== '') {
            $like = SearchSanitizer::likeContains((string) $search);
            $query->where(function ($q) use ($like): void {
                $q->where('name', 'ilike', $like)
                    ->orWhere('email', 'ilike', $like);
            });
        }

        $callback = static function () use ($query): void {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                return;
            }

            $writer = Writer::createFromStream($stream);
            $writer->insertOne(['name', 'email', 'phone', 'whatsapp', 'company', 'created_at']);

            foreach ($query->cursor() as $contact) {
                $writer->insertOne([
                    $contact->name,
                    $contact->email,
                    $contact->phone,
                    $contact->whatsapp,
                    $contact->company?->name,
                    $contact->created_at?->toIso8601String(),
                ]);
            }

            fclose($stream);
        };

        $filename = (string) config('crm.contact_import.export_filename', 'contacts.csv');

        return Response::stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
