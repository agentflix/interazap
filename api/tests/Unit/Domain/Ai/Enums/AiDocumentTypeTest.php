<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Ai\Enums;

use Domain\Ai\Enums\AiDocumentType;

describe('AiDocumentType', function (): void {
    describe('fromExtension()', function (): void {
        it('returns TXT for txt extension', function (): void {
            expect(AiDocumentType::fromExtension('txt'))->toBe(AiDocumentType::TXT);
            expect(AiDocumentType::fromExtension('.txt'))->toBe(AiDocumentType::TXT);
            expect(AiDocumentType::fromExtension('TXT'))->toBe(AiDocumentType::TXT);
        });

        it('returns CSV for csv extension', function (): void {
            expect(AiDocumentType::fromExtension('csv'))->toBe(AiDocumentType::CSV);
        });

        it('returns MARKDOWN for md and markdown extensions', function (): void {
            expect(AiDocumentType::fromExtension('md'))->toBe(AiDocumentType::MARKDOWN);
            expect(AiDocumentType::fromExtension('markdown'))->toBe(AiDocumentType::MARKDOWN);
        });

        it('returns JSON for json extension', function (): void {
            expect(AiDocumentType::fromExtension('json'))->toBe(AiDocumentType::JSON);
        });

        it('returns null for unsupported extensions', function (): void {
            expect(AiDocumentType::fromExtension('pdf'))->toBe(AiDocumentType::PDF);
            expect(AiDocumentType::fromExtension('doc'))->toBeNull();
            expect(AiDocumentType::fromExtension('xlsx'))->toBeNull();
        });
    });

    describe('allExtensions()', function (): void {
        it('returns all supported extensions', function (): void {
            $extensions = AiDocumentType::allExtensions();

            expect($extensions)->toContain('txt');
            expect($extensions)->toContain('csv');
            expect($extensions)->toContain('md');
            expect($extensions)->toContain('markdown');
            expect($extensions)->toContain('json');
            expect($extensions)->toContain('pdf');
        });
    });

    describe('mimeTypes()', function (): void {
        it('returns correct mime types for TXT', function (): void {
            expect(AiDocumentType::TXT->mimeTypes())->toContain('text/plain');
        });

        it('returns correct mime types for CSV', function (): void {
            expect(AiDocumentType::CSV->mimeTypes())->toContain('text/csv');
        });

        it('returns correct mime types for JSON', function (): void {
            expect(AiDocumentType::JSON->mimeTypes())->toContain('application/json');
        });

        it('returns correct mime types for PDF', function (): void {
            expect(AiDocumentType::PDF->mimeTypes())->toContain('application/pdf');
        });
    });

    describe('label()', function (): void {
        it('returns human readable labels', function (): void {
            expect(AiDocumentType::TXT->label())->toBe('Plain Text');
            expect(AiDocumentType::CSV->label())->toBe('CSV');
            expect(AiDocumentType::MARKDOWN->label())->toBe('Markdown');
            expect(AiDocumentType::JSON->label())->toBe('JSON');
            expect(AiDocumentType::PDF->label())->toBe('PDF');
        });
    });
});
