<?php

declare(strict_types=1);

namespace Domain\Ai\Jobs;

use Domain\Ai\Contracts\AiChunkingServiceInterface;
use Domain\Ai\Contracts\AiEmbeddingServiceInterface;
use Domain\Ai\Enums\AiDocumentType;
use Domain\Ai\Enums\AiEmbeddingStatus;
use Domain\Ai\Events\AiKnowledgeDocumentProcessed;
use Domain\Ai\Models\AiKnowledgeChunk;
use Domain\Ai\Models\AiKnowledgeDocument;
use Domain\Shared\Services\GatewayBroadcastService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser;

/**
 * Job for processing knowledge base documents.
 *
 * Flow:
 * 1. Update status to PROCESSING
 * 2. Read file from storage
 * 3. Extract text based on file type
 * 4. Chunk text using AiChunkingService
 * 5. Generate embeddings for each chunk
 * 6. Save chunks to database
 * 7. Update document status to READY
 */
class AiKnowledgeProcessJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const int BATCH_INSERT_SIZE = 50;

    private const string SECURED_PDF_VENDOR_ERROR = 'Secured pdf file are currently not supported.';

    private const string SECURED_PDF_USER_ERROR = 'PDF protegido por senha não é suportado. Remova a proteção e envie novamente.';

    /**
     * Prevent duplicate document processing.
     */
    public int $uniqueFor = 600;

    /**
     * Maximum attempts before job is marked as failed.
     */
    public int $tries = 3;

    /**
     * Alias kept explicit for readability in this critical job.
     */
    public int $maxTries = 3;

    /**
     * Progressive backoff between retries in seconds.
     *
     * @var list<int>
     */
    public array $backoff = [60, 300, 600];

    public function __construct(
        public readonly string $documentId,
    ) {}

    /**
     * Unique ID based on document ID.
     */
    public function uniqueId(): string
    {
        return $this->documentId;
    }

    /**
     * Execute the job.
     */
    public function handle(
        AiChunkingServiceInterface $chunkingService,
        AiEmbeddingServiceInterface $embeddingService,
        ?GatewayBroadcastService $broadcastService = null,
    ): void {
        $gatewayBroadcastService = $broadcastService ?? app(GatewayBroadcastService::class);

        $document = AiKnowledgeDocument::find($this->documentId);

        if (! $document) {
            Log::warning('Document not found for processing', ['document_id' => $this->documentId]);

            return;
        }

        // Skip if already processed or inactive
        if (! $document->is_active || $document->embedding_status === AiEmbeddingStatus::READY) {
            return;
        }

        try {
            // Update status to processing
            $document->update(['embedding_status' => AiEmbeddingStatus::PROCESSING]);

            // Read file content
            $content = $this->readFileContent($document);

            if (empty($content)) {
                throw new \RuntimeException('File is empty or could not be read');
            }

            // Chunk the content
            $chunks = $chunkingService->chunk($content);

            if (empty($chunks)) {
                throw new \RuntimeException('No chunks generated from content');
            }

            // Delete existing chunks (for reindex)
            AiKnowledgeChunk::where('document_id', $document->id)->delete();

            // Process chunks in batches for embedding
            $batchSize = (int) config('ai.embedding.batch_size', 100);
            $chunkContents = array_map(fn ($c) => $c->content, $chunks);

            $allEmbeddings = [];
            foreach (array_chunk($chunkContents, $batchSize) as $batch) {
                $embeddings = $embeddingService->embedBatch($batch);
                $allEmbeddings = array_merge($allEmbeddings, $embeddings);
            }

            // Save chunks with embeddings
            DB::transaction(function () use ($document, $chunks, $allEmbeddings): void {
                $rows = [];

                foreach ($chunks as $i => $chunk) {
                    $embedding = $allEmbeddings[$i] ?? null;
                    $normalizedEmbedding = is_array($embedding)
                        ? $this->normalizeEmbeddingDimensions($embedding)
                        : null;

                    $rows[] = [
                        'id' => (string) Str::orderedUuid(),
                        'document_id' => $document->id,
                        'tenant_id' => $document->tenant_id,
                        'chunk_index' => $chunk->index,
                        'content' => $chunk->content,
                        'token_count' => $chunk->tokenCount,
                        'embedding' => $normalizedEmbedding ? '['.implode(',', $normalizedEmbedding).']' : null,
                    ];
                }

                foreach (array_chunk($rows, self::BATCH_INSERT_SIZE) as $batchRows) {
                    $placeholders = [];
                    $bindings = [];

                    foreach ($batchRows as $row) {
                        $placeholders[] = '(?, ?, ?, ?, ?, ?, ?::vector, NOW())';
                        $bindings[] = $row['id'];
                        $bindings[] = $row['document_id'];
                        $bindings[] = $row['tenant_id'];
                        $bindings[] = $row['chunk_index'];
                        $bindings[] = $row['content'];
                        $bindings[] = $row['token_count'];
                        $bindings[] = $row['embedding'];
                    }

                    DB::insert(
                        'INSERT INTO ai_knowledge_chunks (id, document_id, tenant_id, chunk_index, content, token_count, embedding, created_at) VALUES '.implode(', ', $placeholders),
                        $bindings,
                    );
                }

                // Update document status
                $document->update([
                    'embedding_status' => AiEmbeddingStatus::READY,
                    'chunk_count' => count($chunks),
                    'error_message' => null,
                ]);
            });

            Log::info('Document processed successfully', [
                'document_id' => $document->id,
                'chunk_count' => count($chunks),
            ]);

            event(new AiKnowledgeDocumentProcessed(
                documentId: (string) $document->id,
                tenantId: (string) $document->tenant_id,
                status: AiEmbeddingStatus::READY,
                chunkCount: count($chunks),
            ));

            $gatewayBroadcastService->broadcastEvent('ai.knowledge.document.processed', [
                'document_id' => (string) $document->id,
                'tenant_id' => (string) $document->tenant_id,
                'status' => AiEmbeddingStatus::READY->value,
                'chunk_count' => count($chunks),
            ], sprintf('tenant:%s', (string) $document->tenant_id));
        } catch (\Exception $e) {
            $normalizedErrorMessage = $this->normalizeErrorMessage($e->getMessage());

            Log::error('Document processing failed', [
                'document_id' => $document->id,
                'error' => $normalizedErrorMessage,
            ]);

            $document->update([
                'embedding_status' => AiEmbeddingStatus::FAILED,
                'error_message' => $normalizedErrorMessage,
            ]);

            if ($this->isNonRetryableException($e)) {
                event(new AiKnowledgeDocumentProcessed(
                    documentId: (string) $document->id,
                    tenantId: (string) $document->tenant_id,
                    status: AiEmbeddingStatus::FAILED,
                    chunkCount: (int) $document->chunk_count,
                    errorMessage: $normalizedErrorMessage,
                ));

                $gatewayBroadcastService->broadcastEvent('ai.knowledge.document.processed', [
                    'document_id' => (string) $document->id,
                    'tenant_id' => (string) $document->tenant_id,
                    'status' => AiEmbeddingStatus::FAILED->value,
                    'chunk_count' => (int) $document->chunk_count,
                    'error_message' => $normalizedErrorMessage,
                ], sprintf('tenant:%s', (string) $document->tenant_id));

                return;
            }

            throw $e;
        }
    }

    /**
     * Read and extract text content from file.
     */
    private function readFileContent(AiKnowledgeDocument $document): string
    {
        $path = $document->file_path;

        if (! Storage::exists($path)) {
            throw new \RuntimeException("File not found: {$path}");
        }

        $content = Storage::get($path);

        if ($content === null) {
            throw new \RuntimeException("Could not read file: {$path}");
        }

        return match ($document->file_type) {
            AiDocumentType::TXT, AiDocumentType::MARKDOWN => $content,
            AiDocumentType::CSV => $this->extractTextFromCsv($content),
            AiDocumentType::JSON => $this->extractTextFromJson($content),
            AiDocumentType::PDF => $this->extractTextFromPdf($content),
            AiDocumentType::URL => $this->extractTextFromHtml($content),
        };
    }

    /**
     * Extract text from PDF binary content.
     */
    private function extractTextFromPdf(string $content): string
    {
        $parser = new Parser;

        try {
            $pdf = $parser->parseContent($content);
        } catch (\Throwable $exception) {
            if ($this->isSecuredPdfError($exception->getMessage())) {
                throw new \RuntimeException(self::SECURED_PDF_USER_ERROR, previous: $exception);
            }

            throw $exception;
        }

        return trim($pdf->getText());
    }

    private function isNonRetryableException(\Throwable $exception): bool
    {
        return $this->isSecuredPdfError($exception->getMessage());
    }

    private function normalizeErrorMessage(string $message): string
    {
        if ($this->isSecuredPdfError($message)) {
            return self::SECURED_PDF_USER_ERROR;
        }

        return $message;
    }

    private function isSecuredPdfError(string $message): bool
    {
        $normalized = strtolower($message);

        return str_contains($normalized, strtolower(self::SECURED_PDF_VENDOR_ERROR))
            || str_contains($normalized, strtolower(self::SECURED_PDF_USER_ERROR));
    }

    /**
     * Normalize embedding vector length to match configured pgvector dimensions.
     *
     * @param  list<float|int>  $embedding
     * @return list<float>
     */
    private function normalizeEmbeddingDimensions(array $embedding): array
    {
        $expectedDimensions = (int) config('ai.embedding.dimensions', 512);

        if ($expectedDimensions <= 0) {
            return array_map('floatval', $embedding);
        }

        $normalized = array_map('floatval', $embedding);
        $actualDimensions = count($normalized);

        if ($actualDimensions === $expectedDimensions) {
            return $normalized;
        }

        Log::warning('Embedding dimensions mismatch detected. Normalizing vector length.', [
            'document_id' => $this->documentId,
            'expected_dimensions' => $expectedDimensions,
            'actual_dimensions' => $actualDimensions,
        ]);

        if ($actualDimensions > $expectedDimensions) {
            return array_slice($normalized, 0, $expectedDimensions);
        }

        return array_pad($normalized, $expectedDimensions, 0.0);
    }

    /**
     * Extract text from CSV content.
     */
    private function extractTextFromCsv(string $content): string
    {
        $rows = [];
        foreach (preg_split('/\r\n|\r|\n/', $content) ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }

            $values = array_map(static fn (string $value): string => trim($value), str_getcsv($line));
            if (count(array_filter($values, static fn (string $value): bool => $value !== '')) === 0) {
                continue;
            }

            $rows[] = $values;
        }

        if (empty($rows)) {
            return '';
        }

        if (! $this->hasHeaderRow($rows)) {
            return implode("\n", array_map(
                static fn (array $row): string => implode(' ', array_filter($row, static fn (string $value): bool => $value !== '')),
                $rows,
            ));
        }

        $headers = array_map(static fn (string $header): string => trim($header), $rows[0]);
        $textParts = [];

        foreach (array_slice($rows, 1) as $row) {
            $pairs = [];
            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }

                $value = $row[$index] ?? '';
                if ($value === '') {
                    continue;
                }

                $pairs[] = sprintf('%s: %s', $header, $value);
            }

            if (! empty($pairs)) {
                $textParts[] = implode(', ', $pairs);
            }
        }

        return implode("\n", $textParts);
    }

    /**
     * Determine whether CSV contains a header row.
     *
     * @param  list<list<string>>  $rows
     */
    private function hasHeaderRow(array $rows): bool
    {
        if (count($rows) < 2) {
            return false;
        }

        $header = $rows[0];

        foreach ($header as $value) {
            if ($value === '' || is_numeric($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Extract text from JSON content.
     */
    private function extractTextFromJson(string $content): string
    {
        $data = json_decode($content, true);

        if (! is_array($data)) {
            return $content;
        }

        return $this->flattenJsonToText($data);
    }

    /**
     * Extract text from HTML content.
     */
    private function extractTextFromHtml(string $content): string
    {
        $withoutScripts = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', ' ', $content) ?? $content;
        $withoutStyles = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', ' ', $withoutScripts) ?? $withoutScripts;
        $plain = strip_tags($withoutStyles);

        return trim(preg_replace('/\s+/u', ' ', $plain) ?? $plain);
    }

    /**
     * Recursively flatten JSON to text.
     *
     * @param  array<mixed>  $data
     */
    private function flattenJsonToText(array $data, string $prefix = ''): string
    {
        $parts = [];

        foreach ($data as $key => $value) {
            $currentKey = $prefix !== '' ? "{$prefix}.{$key}" : (string) $key;

            if (is_array($value)) {
                $parts[] = $this->flattenJsonToText($value, $currentKey);
            } elseif (is_string($value) || is_numeric($value)) {
                $parts[] = "{$currentKey}: {$value}";
            }
        }

        return implode("\n", array_filter($parts));
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $normalizedErrorMessage = $this->normalizeErrorMessage($exception->getMessage());

        Log::error('AiKnowledgeProcessJob permanently failed', [
            'document_id' => $this->documentId,
            'error' => $normalizedErrorMessage,
        ]);

        $document = AiKnowledgeDocument::find($this->documentId);
        if ($document) {
            $document->update([
                'embedding_status' => AiEmbeddingStatus::FAILED,
                'error_message' => 'Processing failed after multiple retries: '.$normalizedErrorMessage,
            ]);

            event(new AiKnowledgeDocumentProcessed(
                documentId: (string) $document->id,
                tenantId: (string) $document->tenant_id,
                status: AiEmbeddingStatus::FAILED,
                chunkCount: (int) $document->chunk_count,
                errorMessage: (string) $document->error_message,
            ));

            app(GatewayBroadcastService::class)->broadcastEvent('ai.knowledge.document.processed', [
                'document_id' => (string) $document->id,
                'tenant_id' => (string) $document->tenant_id,
                'status' => AiEmbeddingStatus::FAILED->value,
                'chunk_count' => (int) $document->chunk_count,
                'error_message' => (string) $document->error_message,
            ], sprintf('tenant:%s', (string) $document->tenant_id));
        }
    }
}
