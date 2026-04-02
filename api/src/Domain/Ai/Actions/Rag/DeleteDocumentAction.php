<?php

declare(strict_types=1);

namespace Domain\Ai\Actions\Rag;

use Domain\Ai\Models\AiKnowledgeDocument;
use Illuminate\Support\Facades\Storage;

/**
 * Action for deleting knowledge documents.
 *
 * Implements soft delete by setting is_active = false.
 * Optionally deletes file from storage.
 */
final class DeleteDocumentAction
{
    /**
     * Delete (soft) a knowledge document.
     *
     * @param  bool  $deleteFile  Whether to also delete the file from storage
     */
    public function execute(AiKnowledgeDocument $document, bool $deleteFile = false): void
    {
        // Soft delete by marking inactive
        $document->update(['is_active' => false]);

        // Optionally delete the file
        if ($deleteFile && Storage::exists($document->file_path)) {
            Storage::delete($document->file_path);
        }
    }

    /**
     * Hard delete a document and its chunks.
     */
    public function forceDelete(AiKnowledgeDocument $document): void
    {
        // Delete file from storage
        if (Storage::exists($document->file_path)) {
            Storage::delete($document->file_path);
        }

        // Delete chunks (handled by model's deleting event)
        $document->delete();
    }
}
