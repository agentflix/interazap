<?php

declare(strict_types=1);

namespace Domain\Gateway\Enums;

/**
 * Gateway service providers categorized by domain.
 *
 * AI: openai, gemini, minimax
 * WhatsApp: zapi, uazapi
 * Payment: asaas
 */
enum GatewayProvider: string
{
    case OPENAI = 'openai';
    case GEMINI = 'gemini';
    case MINIMAX = 'minimax';
    case ZAPI = 'zapi';
    case UAZAPI = 'uazapi';
    case ASAAS = 'asaas';
}
