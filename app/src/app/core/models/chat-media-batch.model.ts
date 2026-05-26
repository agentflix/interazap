import type { CalledMessage } from '@core/models/called-message.model';

/**
 * Fase de um envio em lote de mídia.
 *
 * - `uploading`: fazendo upload para o servidor
 * - `uploaded`: upload concluído, aguardando envio
 * - `sent`: enviado ao destinatário com sucesso
 * - `failed`: falha no envio
 */
export type MediaBatchPhase = 'uploading' | 'uploaded' | 'sent' | 'failed';

/**
 * Evento emitido durante o envio em lote de arquivos de mídia.
 *
 * Contexto: usado pelo serviço de chat para notificar a interface sobre o
 * progresso individual de cada arquivo em um envio múltiplo.
 */
export interface MediaBatchEvent {
  /** Identificador único do arquivo no lote. */
  id: string;
  /** Fase atual do envio. */
  phase: MediaBatchPhase;
  /** Bytes já enviados (durante upload). */
  loaded?: number;
  /** Total de bytes do arquivo (durante upload). */
  total?: number;
  /** Mensagem resultante (preenchida quando phase = 'sent'). */
  message?: CalledMessage;
}
