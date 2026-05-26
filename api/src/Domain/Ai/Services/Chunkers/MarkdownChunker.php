<?php

declare(strict_types=1);

namespace Domain\Ai\Services\Chunkers;

use Domain\Ai\Contracts\Chunkers\ChunkerStrategyInterface;
use Domain\Ai\DTOs\ChunkDTO;

/**
 * Estratégia de chunking otimizada para arquivos Markdown.
 *
 * Divide preferencialmente por headings ## e ###, preservando blocos
 * de código intactos. Mantém o heading pai no início do chunk filho
 * quando a seção é dividida. Agrupa seções até ~500 tokens com
 * sobreposição de 50 tokens.
 */
final class MarkdownChunker implements ChunkerStrategyInterface
{
    private const int CHUNK_SIZE_TOKENS = 500;

    private const int OVERLAP_TOKENS = 50;

    private const float DEFAULT_CHARS_PER_TOKEN = 3.5;

    /**
     * @return list<ChunkDTO>
     */
    public function chunk(string $text): array
    {
        $text = trim($text);

        if ($text === '') {
            return [];
        }

        $sections = $this->splitByHeadings($text);

        return $this->groupSectionsIntoChunks($sections);
    }

    /**
     * Divide o texto Markdown por headings, preservando blocos de código.
     *
     * @return list<array{heading: string, body: string}>
     */
    private function splitByHeadings(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $text);
        if ($lines === false) {
            $lines = [$text];
        }

        $sections = [];
        $currentHeading = '';
        $currentBody = [];
        $inCodeBlock = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Toggle code block state
            if (str_starts_with($trimmed, '```')) {
                $inCodeBlock = ! $inCodeBlock;
                $currentBody[] = $line;

                continue;
            }

            // If we're in a code block, just append
            if ($inCodeBlock) {
                $currentBody[] = $line;

                continue;
            }

            // Check for heading
            if (preg_match('/^(#{2,3})\s+(.+)$/', $trimmed, $matches)) {
                // Save previous section
                if ($currentHeading !== '' || $currentBody !== []) {
                    $sections[] = [
                        'heading' => $currentHeading,
                        'body' => implode("\n", $currentBody),
                    ];
                }

                $currentHeading = $matches[0];
                $currentBody = [];

                continue;
            }

            $currentBody[] = $line;
        }

        // Save last section
        if ($currentHeading !== '' || $currentBody !== []) {
            $sections[] = [
                'heading' => $currentHeading,
                'body' => implode("\n", $currentBody),
            ];
        }

        // If no headings found, treat entire text as one section
        if ($sections === []) {
            $sections[] = [
                'heading' => '',
                'body' => $text,
            ];
        }

        return $sections;
    }

    /**
     * Agrupa seções em chunks, mantendo o prefixo do heading.
     *
     * @param  list<array{heading: string, body: string}>  $sections
     * @return list<ChunkDTO>
     */
    private function groupSectionsIntoChunks(array $sections): array
    {
        $chunks = [];
        $currentChunk = [];
        $currentTokens = 0;
        $currentHeading = '';
        $chunkIndex = 0;

        foreach ($sections as $section) {
            $heading = $section['heading'];
            $body = trim($section['body']);
            $sectionText = $heading !== '' && $body !== ''
                ? $heading."\n".$body
                : ($heading !== '' ? $heading : $body);

            if ($sectionText === '') {
                continue;
            }

            $sectionTokens = $this->estimateTokens($sectionText);

            // If a single section exceeds chunk size, split it by sentences
            if ($sectionTokens > self::CHUNK_SIZE_TOKENS) {
                // Flush current chunk first
                if ($currentChunk !== []) {
                    $chunks[] = $this->createChunk($currentChunk, $chunkIndex++);
                    $currentChunk = [];
                    $currentTokens = 0;
                }

                // Split section by sentences and group
                $sentences = $this->splitBySentences($sectionText);
                $subChunk = [];
                $subTokens = 0;

                foreach ($sentences as $sentence) {
                    $sentenceTokens = $this->estimateTokens($sentence);

                    if ($subTokens + $sentenceTokens > self::CHUNK_SIZE_TOKENS && $subChunk !== []) {
                        $chunks[] = $this->createChunk($subChunk, $chunkIndex++);

                        // Start new sub-chunk with heading + overlap
                        $overlap = $this->getOverlapSentences($subChunk);
                        $subChunk = array_merge(
                            $heading !== '' ? [$heading] : [],
                            $overlap
                        );
                        $subTokens = array_sum(array_map(
                            fn (string $s): int => $this->estimateTokens($s),
                            $subChunk
                        ));
                    }

                    $subChunk[] = $sentence;
                    $subTokens += $sentenceTokens;
                }

                if ($subChunk !== []) {
                    $chunks[] = $this->createChunk($subChunk, $chunkIndex++);
                }

                $currentHeading = $heading;

                continue;
            }

            // Check if adding this section exceeds the limit
            if ($currentTokens + $sectionTokens > self::CHUNK_SIZE_TOKENS && $currentChunk !== []) {
                $chunks[] = $this->createChunk($currentChunk, $chunkIndex++);

                // Start new chunk with heading + overlap from previous
                $overlap = $this->getOverlapSentences($currentChunk);
                $currentChunk = array_merge(
                    $heading !== '' ? [$heading] : [],
                    $overlap
                );
                $currentTokens = array_sum(array_map(
                    fn (string $s): int => $this->estimateTokens($s),
                    $currentChunk
                ));
            }

            $currentChunk[] = $sectionText;
            $currentTokens += $sectionTokens;
            $currentHeading = $heading;
        }

        if ($currentChunk !== []) {
            $chunks[] = $this->createChunk($currentChunk, $chunkIndex);
        }

        return $chunks;
    }

    /**
     * Cria um ChunkDTO a partir de partes de texto.
     *
     * @param  list<string>  $parts
     */
    private function createChunk(array $parts, int $index): ChunkDTO
    {
        $content = implode("\n\n", $parts);

        return new ChunkDTO(
            index: $index,
            content: $content,
            tokenCount: $this->estimateTokens($content),
        );
    }

    /**
     * Divide o texto em sentenças por pontuação terminal.
     *
     * @return list<string>
     */
    private function splitBySentences(string $text): array
    {
        $sentences = preg_split('/(?<=[.!?])\s+/', $text);
        if ($sentences === false) {
            return [$text];
        }

        return array_values(array_filter(
            array_map('trim', $sentences),
            fn (string $s): bool => $s !== ''
        ));
    }

    /**
     * Retorna sentenças finais do chunk para sobreposição do próximo.
     *
     * @param  list<string>  $sentences
     * @return list<string>
     */
    private function getOverlapSentences(array $sentences): array
    {
        $overlap = [];
        $overlapTokens = 0;

        for ($i = count($sentences) - 1; $i >= 0; $i--) {
            $sentenceTokens = $this->estimateTokens($sentences[$i]);

            if ($overlapTokens + $sentenceTokens > self::OVERLAP_TOKENS) {
                break;
            }

            // Skip heading lines in overlap (they start with #)
            if (preg_match('/^#{2,3}\s/', $sentences[$i])) {
                continue;
            }

            array_unshift($overlap, $sentences[$i]);
            $overlapTokens += $sentenceTokens;
        }

        return $overlap;
    }

    /** Estima a quantidade de tokens de um texto via divisão por caracteres. */
    private function estimateTokens(string $text): int
    {
        $charsPerToken = (float) config('ai.chunking.chars_per_token', self::DEFAULT_CHARS_PER_TOKEN);

        return (int) ceil(mb_strlen($text, 'UTF-8') / $charsPerToken);
    }
}
