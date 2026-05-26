import { Injectable } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';

/**
 * Serviço de configuração do gateway.
 *
 * Contexto: abstrai a leitura de variáveis de ambiente relacionadas a canais Redis PubSub,
 * nomes de streams e configurações de ambiente para uso em serviços do gateway.
 */
@Injectable()
export class GatewayConfigService {
  /**
   * Inicializa o serviço de configuração com o ConfigService do NestJS para acesso às variáveis de ambiente.
   *
   * @param configService - Instância do ConfigService do NestJS (injetada)
   */
  constructor(private readonly configService: ConfigService) {}

  /**
   * Verifica se a aplicação está sendo executada em ambiente de teste.
   * Utiliza NODE_ENV via ConfigService e JEST_WORKER_ID definido pelo runtime do Jest.
   *
   * @returns true se NODE_ENV for 'test' ou se estiver sendo executado via Jest
   */
  isTestEnvironment(): boolean {
    const nodeEnv = this.configService.get<string>('NODE_ENV');
    return nodeEnv === 'test' || process.env['JEST_WORKER_ID'] !== undefined;
  }

  /**
   * Canal Redis PubSub utilizado para distribuir eventos WebSocket a todas as instâncias do gateway.
   *
   * @returns Nome do canal; padrão: `ws.events`
   */
  get wsEventsChannel(): string {
    return this.configService.get<string>('WS_EVENTS_CHANNEL') ?? 'ws.events';
  }

  /**
   * Nome do stream Redis para mensagens de chat recebidas pelo gateway.
   *
   * @returns Nome do stream; padrão: `chat.inbound_message_received`
   */
  get chatInboundStream(): string {
    return (
      this.configService.get<string>('CHAT_INBOUND_STREAM') ??
      'chat.inbound_message_received'
    );
  }

  /**
   * Nome do stream Redis para mensagens de chat enviadas pelo gateway.
   *
   * @returns Nome do stream; padrão: `chat.outbound_message`
   */
  get chatOutboundStream(): string {
    return (
      this.configService.get<string>('CHAT_OUTBOUND_STREAM') ??
      'chat.outbound_message'
    );
  }

  /**
   * Nome do stream Redis para atualizações de status de mensagens enviadas (entregue, lido, falhou).
   *
   * @returns Nome do stream; padrão: `chat.outbound_message_status`
   */
  get chatOutboundStatusStream(): string {
    return (
      this.configService.get<string>('CHAT_OUTBOUND_STATUS_STREAM') ??
      'chat.outbound_message_status'
    );
  }

  /**
   * Nome do stream Redis para eventos de pagamento de billing recebidos pelo gateway.
   *
   * @returns Nome do stream; padrão: `billing.payment_received`
   */
  get billingStreamName(): string {
    return (
      this.configService.get<string>('BILLING_STREAM_NAME') ??
      'billing.payment_received'
    );
  }

  /**
   * Nome do stream Redis para requisições de invocação de ferramentas de IA despachadas pelo gateway.
   *
   * @returns Nome do stream; padrão: `ai.tool.request`
   */
  get aiToolRequestStream(): string {
    return (
      this.configService.get<string>('AI_TOOL_REQUEST_STREAM') ??
      'ai.tool.request'
    );
  }

  /**
   * Canal Redis PubSub utilizado pela API para solicitar cancelamento de execuções de IA ativas.
   *
   * @returns Nome do canal; padrão: `ai.run.cancel_requested`
   */
  get aiRunCancelChannel(): string {
    return (
      this.configService.get<string>('AI_RUN_CANCEL_CHANNEL') ??
      'ai.run.cancel_requested'
    );
  }
}
