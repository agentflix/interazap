<?php

declare(strict_types=1);

namespace Domain\Gateway\Enums;

/**
 * Providers de serviços externos suportados pelo gateway, categorizados por domínio.
 *
 * IA: openai, gemini, minimax
 * WhatsApp: zapi, uazapi
 * Pagamento: asaas
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
