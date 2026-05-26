/**
 * Payload para envio de indicador de presença (digitando/gravando) no chat.
 *
 * Contexto: enviado pelo serviço de chat para informar ao contato
 * que o agente está digitando ou gravando áudio.
 */
export interface PresencePayload {
  /** Tipo de presença: digitando, gravando ou pausado. */
  presence: 'composing' | 'recording' | 'paused';
  /** Duração em milissegundos da presença (opcional). */
  delay?: number;
}

/**
 * Resposta da API após envio de indicador de presença.
 */
export interface PresenceResponse {
  success: boolean;
  message: string;
  data?: unknown;
}
