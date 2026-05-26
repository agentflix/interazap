<?php

declare(strict_types=1);

namespace Domain\Ai\Enums;

/**
 * Provedores de IA suportados pela plataforma.
 *
 * Cada caso representa um fornecedor de modelos de linguagem (LLM)
 * com o qual o sistema pode integrar para executar completions.
 */
enum AiProviderType: string
{
    case OPENAI = 'openai';
    case ANTHROPIC = 'anthropic';
    case AZURE_OPENAI = 'azure_openai';
    case GOOGLE = 'google';
    case GROQ = 'groq';
    case MINIMAX = 'minimax';

    /**
     * Retorna o nome de exibição do provedor.
     */
    public function label(): string
    {
        return match ($this) {
            self::OPENAI => 'OpenAI',
            self::ANTHROPIC => 'Anthropic',
            self::AZURE_OPENAI => 'Azure OpenAI',
            self::GOOGLE => 'Google AI',
            self::GROQ => 'Groq',
            self::MINIMAX => 'MiniMax',
        };
    }

    /**
     * Retorna o modelo padrão do provedor.
     */
    public function defaultModel(): string
    {
        return match ($this) {
            self::OPENAI => 'gpt-4o',
            self::ANTHROPIC => 'claude-3-sonnet-20240229',
            self::AZURE_OPENAI => 'gpt-4o',
            self::GOOGLE => 'gemini-pro',
            self::GROQ => 'llama-3.1-70b-versatile',
            self::MINIMAX => 'MiniMax-M2.5',
        };
    }
}
