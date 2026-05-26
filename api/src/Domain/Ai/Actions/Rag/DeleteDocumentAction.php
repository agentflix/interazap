<?php

declare(strict_types=1);

namespace Domain\Ai\Actions\Rag;

use Domain\Ai\Models\AiKnowledgeDocument;
use Illuminate\Support\Facades\Storage;

/**
 * Action para exclusão de documentos de conhecimento.
 *
 * Implementa soft delete via is_active = false. Opcionalmente
 * remove o arquivo físico do Storage.
 */
final class DeleteDocumentAction
{
    /**
     * Realiza a exclusão lógica (soft delete) de um documento de conhecimento.
     *
     * @param  AiKnowledgeDocument  $document  Documento a excluir.
     * @param  bool  $deleteFile  Se verdadeiro, também remove o arquivo do Storage.
     */
    public function execute(AiKnowledgeDocument $document, bool $deleteFile = false): void
    {
        // Exclusão lógica: marca como inativo
        $document->update(['is_active' => false]);

        // Optionally delete the file
        if ($deleteFile && Storage::exists($document->file_path)) {
            Storage::delete($document->file_path);
        }
    }

    /**
     * Exclui permanentemente um documento e seus chunks do banco e do Storage.
     *
     * A exclusão dos chunks é tratada pelo evento deleting do model.
     *
     * @param  AiKnowledgeDocument  $document  Documento a excluir permanentemente.
     */
    public function forceDelete(AiKnowledgeDocument $document): void
    {
        // Remove arquivo físico do Storage
        if (Storage::exists($document->file_path)) {
            Storage::delete($document->file_path);
        }

        // Delete chunks (handled by model's deleting event)
        $document->delete();
    }
}
