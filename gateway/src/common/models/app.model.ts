/**
 * Modelos do módulo raiz da aplicação gateway.
 * Define tipos auxiliares utilizados na configuração do AppModule.
 */

/**
 * Representa o objeto de requisição utilizado pelo ThrottlerModule
 * para identificar o cliente em contextos HTTP e WebSocket.
 */
export type ThrottlerTrackerRequest = {
  /** Usuário autenticado, se disponível */
  user?: {
    /** Subject (sub) do JWT — identificador único do usuário */
    sub?: string;
  };
  /** ID do cliente WebSocket, se disponível */
  clientId?: string;
  /** Endereço IP do cliente */
  ip?: string;
};
