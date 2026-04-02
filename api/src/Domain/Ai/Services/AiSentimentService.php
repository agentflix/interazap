<?php

declare(strict_types=1);

namespace Domain\Ai\Services;

use Domain\Ai\Contracts\AIServiceInterface;
use Domain\Ai\DTOs\SentimentResultDTO;
use Domain\Ai\Enums\SentimentLevel;
use Domain\Gateway\DTOs\AI\AICompletionRequest;

/**
 * Motor de análise de sentimento com estratégia híbrida:
 * keywords (baixo custo) + fallback LLM (alta precisão).
 */
final class AiSentimentService
{
    /** @var array<string, int> */
    private const NEGATIVE_KEYWORDS = [
        'cancelar' => 25,
        'cancela' => 25,
        'reclamar' => 20,
        'reclamacao' => 20,
        'pessimo' => 30,
        'horrivel' => 30,
        'absurdo' => 25,
        'vergonha' => 25,
        'procon' => 35,
        'reclame aqui' => 35,
        'advogado' => 30,
        'processo' => 20,
        'nunca mais' => 25,
        'desistir' => 20,
        'nao funciona' => 20,
        'não funciona' => 20,
        'nao resolve' => 25,
        'não resolve' => 25,
        'demora' => 15,
        'lixo' => 30,
    ];

    /** @var array<string, int> */
    private const POSITIVE_KEYWORDS = [
        'obrigado' => -15,
        'excelente' => -20,
        'otimo' => -15,
        'ótimo' => -15,
        'parabens' => -20,
        'parabéns' => -20,
        'adorei' => -15,
        'perfeito' => -20,
        'recomendo' => -15,
        'satisfeito' => -15,
    ];

    public function __construct(
        private readonly AIServiceInterface $aiService,
    ) {}

    /**
     * Analisar sentimento por palavras-chave.
     */
    public function analyzeKeywords(string $text): SentimentResultDTO
    {
        $normalized = mb_strtolower(trim($text));
        $score = 50;
        $keywordMatches = 0;

        foreach (self::NEGATIVE_KEYWORDS as $keyword => $weight) {
            if (str_contains($normalized, $keyword)) {
                $score += $weight;
                $keywordMatches++;
            }
        }

        foreach (self::POSITIVE_KEYWORDS as $keyword => $weight) {
            if (str_contains($normalized, $keyword)) {
                $score += $weight;
                $keywordMatches++;
            }
        }

        $score = $this->normalizeScore($score);

        return new SentimentResultDTO(
            score: $score,
            level: SentimentLevel::fromScore($score),
            confidence: $this->calculateConfidence($normalized, $keywordMatches),
            method: 'keywords',
        );
    }

    /**
     * Analisar sentimento via LLM quando keyword matching for inconclusivo.
     */
    public function analyzeLLM(string $text, string $tenantId): SentimentResultDTO
    {
        $prompt = <<<PROMPT
Analyze the sentiment of this customer message in a support context.
Return ONLY a JSON object: {"score": <0-100>, "reasoning": "<brief>"}
Score guide: 0=very positive, 50=neutral, 100=very negative/angry.

Message: {$text}
PROMPT;

        $response = $this->aiService->complete(
            AICompletionRequest::create([
                ['role' => 'system', 'content' => 'You are a sentiment analysis engine. Respond only in JSON.'],
                ['role' => 'user', 'content' => $prompt],
            ])->withMaxTokens(100)
        );

        $decoded = json_decode($response->content, true);
        $score = is_array($decoded) && isset($decoded['score'])
            ? (int) $decoded['score']
            : 50;

        $score = $this->normalizeScore($score);

        return new SentimentResultDTO(
            score: $score,
            level: SentimentLevel::fromScore($score),
            confidence: 0.90,
            method: 'llm',
        );
    }

    /**
     * Executar análise em duas camadas.
     */
    public function analyze(string $text, string $tenantId): SentimentResultDTO
    {
        $keywordResult = $this->analyzeKeywords($text);

        if ($keywordResult->confidence >= 0.7) {
            return $keywordResult;
        }

        return $this->analyzeLLM($text, $tenantId);
    }

    /**
     * Calcular confiança com base em densidade de sinais e tamanho do texto.
     */
    private function calculateConfidence(string $text, int $keywordMatches): float
    {
        if ($keywordMatches === 0) {
            return 0.20;
        }

        $wordCount = count(array_filter(preg_split('/\s+/', $text) ?: []));

        if ($keywordMatches === 1) {
            return $wordCount <= 3 ? 0.60 : 0.70;
        }

        return min(0.95, 0.75 + (0.05 * min(3, $keywordMatches - 2)));
    }

    /**
     * Garantir score no range 0-100.
     */
    private function normalizeScore(int $score): int
    {
        return max(0, min(100, $score));
    }
}
