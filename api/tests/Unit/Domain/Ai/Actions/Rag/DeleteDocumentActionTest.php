<?php

declare(strict_types=1);

use Domain\Ai\Actions\Rag\DeleteDocumentAction;
use Domain\Ai\Models\AiKnowledgeDocument;
use Illuminate\Support\Facades\Storage;

uses(\Illuminate\Foundation\Testing\LazilyRefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');
});

describe('execute', function (): void {
    it('marks document as inactive', function (): void {
        $document = AiKnowledgeDocument::factory()->create([
            'is_active' => true,
        ]);

        $action = new DeleteDocumentAction;
        $action->execute($document);

        expect($document->fresh()->is_active)->toBeFalse();
    });

    it('does not delete file by default', function (): void {
        Storage::put('knowledge/test.txt', 'content');

        $document = AiKnowledgeDocument::factory()->create([
            'is_active' => true,
            'file_path' => 'knowledge/test.txt',
        ]);

        $action = new DeleteDocumentAction;
        $action->execute($document);

        Storage::assertExists('knowledge/test.txt');
    });

    it('deletes file when deleteFile is true', function (): void {
        Storage::put('knowledge/test.txt', 'content');

        $document = AiKnowledgeDocument::factory()->create([
            'is_active' => true,
            'file_path' => 'knowledge/test.txt',
        ]);

        $action = new DeleteDocumentAction;
        $action->execute($document, deleteFile: true);

        Storage::assertMissing('knowledge/test.txt');
    });

    it('handles missing file gracefully', function (): void {
        $document = AiKnowledgeDocument::factory()->create([
            'is_active' => true,
            'file_path' => 'knowledge/nonexistent.txt',
        ]);

        $action = new DeleteDocumentAction;
        $action->execute($document, deleteFile: true);

        expect($document->fresh()->is_active)->toBeFalse();
    });
});

describe('forceDelete', function (): void {
    it('deletes document and file from storage', function (): void {
        Storage::put('knowledge/test.txt', 'content');

        $document = AiKnowledgeDocument::factory()->create([
            'file_path' => 'knowledge/test.txt',
        ]);
        $documentId = $document->id;

        $action = new DeleteDocumentAction;
        $action->forceDelete($document);

        Storage::assertMissing('knowledge/test.txt');
        expect(\Domain\Ai\Models\AiKnowledgeDocument::query()->find($documentId))->toBeNull();
    });

    it('handles missing file during force delete', function (): void {
        $document = AiKnowledgeDocument::factory()->create([
            'file_path' => 'knowledge/nonexistent.txt',
        ]);
        $documentId = $document->id;

        $action = new DeleteDocumentAction;
        $action->forceDelete($document);

        expect(\Domain\Ai\Models\AiKnowledgeDocument::query()->find($documentId))->toBeNull();
    });
});
