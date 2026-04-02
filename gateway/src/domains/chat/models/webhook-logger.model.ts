/**
 * Modelos do dominio Chat/Webhook Logger do gateway.
 * Tipos internos da fila de escrita do logger de webhook.
 */

/**
 * Entrada da fila de escrita em arquivo para logs de webhook.
 */
export type QueueEntry = {
  /** Linha serializada que sera persistida no arquivo. */
  readonly line: string;
  /** Caminho completo do arquivo de destino. */
  readonly targetFile: string;
};
