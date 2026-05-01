<?php

declare(strict_types=1);

namespace Domain\Ai\Enums;

/**
 * Tipos de documentos suportados para upload na Knowledge Base.
 *
 * Representa os formatos de arquivo aceitos pelo sistema RAG:
 * - TXT: Arquivos de texto puro
 * - CSV: Arquivos de dados tabulares
 * - MARKDOWN: Documentos formatados em Markdown
 * - JSON: Arquivos de dados estruturados
 */
enum AiDocumentType: string
{
    case TXT = 'txt';
    case CSV = 'csv';
    case MARKDOWN = 'markdown';
    case JSON = 'json';
    case PDF = 'pdf';
    case URL = 'url';

    /**
     * Retorna os tipos MIME aceitos para este tipo de documento.
     *
     * @return list<string>
     */
    public function mimeTypes(): array
    {
        return match ($this) {
            self::TXT => ['text/plain'],
            self::CSV => ['text/csv', 'application/csv'],
            self::MARKDOWN => ['text/markdown', 'text/plain'],
            self::JSON => ['application/json', 'text/json'],
            self::PDF => ['application/pdf'],
            self::URL => [],
        };
    }

    /**
     * Retorna as extensões de arquivo aceitas.
     *
     * @return list<string>
     */
    public function extensions(): array
    {
        return match ($this) {
            self::TXT => ['txt'],
            self::CSV => ['csv'],
            self::MARKDOWN => ['md', 'markdown'],
            self::JSON => ['json'],
            self::PDF => ['pdf'],
            self::URL => [],
        };
    }

    /**
     * Cria o enum a partir da extensão do arquivo.
     */
    public static function fromExtension(string $extension): ?self
    {
        $extension = strtolower(trim($extension, '.'));

        return match ($extension) {
            'txt' => self::TXT,
            'csv' => self::CSV,
            'md', 'markdown' => self::MARKDOWN,
            'json' => self::JSON,
            'pdf' => self::PDF,
            default => null,
        };
    }

    /**
     * Retorna a cor de badge para exibição.
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::TXT => 'gray',
            self::CSV => 'green',
            self::MARKDOWN => 'blue',
            self::JSON => 'purple',
            self::PDF => 'red',
            self::URL => 'cyan',
        };
    }

    /**
     * Retorna o label para exibição.
     */
    public function label(): string
    {
        return match ($this) {
            self::TXT => 'Plain Text',
            self::CSV => 'CSV',
            self::MARKDOWN => 'Markdown',
            self::JSON => 'JSON',
            self::PDF => 'PDF',
            self::URL => 'URL',
        };
    }

    /**
     * Retorna todos os valores do enum.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Retorna todos os tipos MIME aceitos pelo sistema.
     *
     * @return list<string>
     */
    public static function allMimeTypes(): array
    {
        $mimeTypes = [];
        foreach (self::cases() as $case) {
            $mimeTypes = array_merge($mimeTypes, $case->mimeTypes());
        }

        return array_values(array_unique($mimeTypes));
    }

    /**
     * Retorna todas as extensões aceitas pelo sistema.
     *
     * @return list<string>
     */
    public static function allExtensions(): array
    {
        $extensions = [];
        foreach (self::cases() as $case) {
            $extensions = array_merge($extensions, $case->extensions());
        }

        return array_values(array_unique($extensions));
    }
}
