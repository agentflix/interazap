<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\Ai\Models\AiKnowledgeChunk;
use Domain\Ai\Models\AiKnowledgeDocument;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seed tenant knowledge documents and chunks.
 */
class AiKnowledgeDocumentSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = PlatformTenant::query()->limit(100)->get();

        if ($tenants->isEmpty()) {
            $this->command->warn('No tenants found. Skipping AiKnowledgeDocumentSeeder.');

            return;
        }

        $templates = [
            ['name' => 'FAQ Atendimento', 'file_type' => 'markdown', 'status' => 'ready', 'chunks' => [15, 25]],
            ['name' => 'Catálogo de Produtos', 'file_type' => 'json', 'status' => 'ready', 'chunks' => [30, 50]],
            ['name' => 'Política de Devolução', 'file_type' => 'txt', 'status' => 'ready', 'chunks' => [8, 12]],
            ['name' => 'Manual do Vendedor', 'file_type' => 'markdown', 'status' => 'ready', 'chunks' => [20, 35]],
            ['name' => 'Tabela de Preços 2026', 'file_type' => 'csv', 'status' => 'ready', 'chunks' => [10, 15]],
            ['name' => 'Termos de Uso (draft)', 'file_type' => 'txt', 'status' => 'pending', 'chunks' => [0, 0]],
            ['name' => 'Treinamento Novo (proc)', 'file_type' => 'markdown', 'status' => 'processing', 'chunks' => [0, 0]],
            ['name' => 'Upload com Erro', 'file_type' => 'json', 'status' => 'failed', 'chunks' => [0, 0]],
        ];

        $documentsCount = 0;
        $chunksCount = 0;

        foreach ($tenants as $tenant) {
            foreach ($templates as $template) {
                $extension = $this->resolveExtension($template['file_type']);
                $filename = Str::slug($template['name']).'.'.$extension;
                $chunkCount = random_int($template['chunks'][0], $template['chunks'][1]);

                $document = AiKnowledgeDocument::query()->firstOrNew([
                    'tenant_id' => $tenant->id,
                    'name' => $template['name'],
                ]);

                if (! $document->exists) {
                    $document->id = (string) Str::orderedUuid();
                }

                $document->fill([
                    'original_filename' => $filename,
                    'file_path' => sprintf('knowledge/%s/%s', $tenant->id, $filename),
                    'file_size_bytes' => random_int(15000, 400000),
                    'file_type' => $template['file_type'],
                    'version' => 1,
                    'replaced_by' => null,
                    'chunk_count' => $chunkCount,
                    'embedding_status' => $template['status'],
                    'error_message' => $template['status'] === 'failed' ? 'Failed to parse JSON structure.' : null,
                    'metadata' => ['seed_source' => 'ai_module_seeder'],
                    'is_active' => true,
                ]);

                $document->save();

                $documentsCount++;

                AiKnowledgeChunk::query()->where('document_id', $document->id)->delete();

                if ($chunkCount > 0) {
                    $now = now();
                    $rows = [];
                    for ($i = 0; $i < $chunkCount; $i++) {
                        $rows[] = [
                            'id' => (string) Str::orderedUuid(),
                            'document_id' => $document->id,
                            'tenant_id' => $tenant->id,
                            'chunk_index' => $i,
                            'content' => fake()->realTextBetween(380, 1800),
                            'token_count' => random_int(100, 500),
                            'embedding' => null,
                            'created_at' => $now,
                        ];
                    }

                    AiKnowledgeChunk::query()->insert($rows);
                    $chunksCount += count($rows);
                }
            }
        }

        $this->command->info(sprintf('AI Knowledge seeded: %d documents, %d chunks', $documentsCount, $chunksCount));
    }

    private function resolveExtension(string $fileType): string
    {
        return match ($fileType) {
            'markdown' => 'md',
            default => $fileType,
        };
    }
}
