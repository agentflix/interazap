<?php

declare(strict_types=1);

namespace Domain\Ai\Console\Commands;

use Domain\Ai\Jobs\AiKnowledgeProcessJob;
use Domain\Ai\Models\AiKnowledgeDocument;
use Illuminate\Console\Command;

/**
 * Reindexa todos os documentos ativos da base de conhecimento.
 */
class ReindexAllKnowledgeDocumentsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'ai:reindex-all
                            {--tenant= : Reindex only a specific tenant ID}
                            {--chunk=200 : Number of records per chunk}
                            {--sync : Process inline instead of dispatching jobs}';

    /**
     * @var string
     */
    protected $description = 'Reindex all active AI knowledge documents with current embedding dimensions';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $chunkSize = (int) $this->option('chunk');
        $sync = (bool) $this->option('sync');

        $query = AiKnowledgeDocument::query()
            ->where('is_active', true)
            ->when(is_string($tenantId) && $tenantId !== '', fn ($builder) => $builder->where('tenant_id', $tenantId))
            ->orderBy('id');

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('No active knowledge documents found for reindexing.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Reindexing %d active knowledge documents...', $total));

        $processed = 0;
        $query->chunkById($chunkSize, function ($documents) use (&$processed, $sync): void {
            foreach ($documents as $document) {
                if ($sync) {
                    AiKnowledgeProcessJob::dispatchSync((string) $document->id, (string) $document->tenant_id);
                } else {
                    AiKnowledgeProcessJob::dispatch((string) $document->id, (string) $document->tenant_id);
                }

                $processed++;
            }
        }, 'id');

        $mode = $sync ? 'sync' : 'queued';
        $this->info(sprintf('Reindex dispatch complete: %d documents (%s mode).', $processed, $mode));

        return self::SUCCESS;
    }
}
