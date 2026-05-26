<?php

declare(strict_types=1);

namespace Domain\Chat\Enums;

/**
 * Status de entrega de uma mensagem de chat.
 *
 * Representa o ciclo de vida do envio: da fila interna até a confirmação
 * de leitura/reprodução pelo destinatário no WhatsApp.
 */
enum MessageDeliveryStatus: string
{
    /** Na fila interna, ainda não enviado ao provedor. */
    case PENDING = 'pending';
    /** Aceito pelo provedor, aguardando entrega ao dispositivo. */
    case SENT = 'sent';
    /** Entregue ao dispositivo do destinatário. */
    case DELIVERED = 'delivered';
    /** Lido pelo destinatário. */
    case READ = 'read';
    /** Mensagem de voz reproduzida pelo destinatário. */
    case PLAYED = 'played';
    /** Falha permanente no envio. */
    case FAILED = 'failed';

    /** Converte status retornado pela Uazapi para o enum interno. */
    public static function fromUzapi(string $status): self
    {
        return match (strtolower($status)) {
            'pending', 'queued' => self::PENDING,
            'sent' => self::SENT,
            'delivered' => self::DELIVERED,
            'read' => self::READ,
            'played' => self::PLAYED,
            'error', 'failed' => self::FAILED,
            default => self::PENDING,
        };
    }

    /** Converte status retornado pela Z-API para o enum interno. */
    public static function fromZApi(string $status): self
    {
        return match (strtoupper($status)) {
            'PENDING' => self::PENDING,
            'SENT' => self::SENT,
            'RECEIVED' => self::DELIVERED,
            'READ' => self::READ,
            'PLAYED' => self::PLAYED,
            'ERROR', 'FAILED' => self::FAILED,
            default => self::PENDING,
        };
    }
}
