/**
 * Modelos do dominio Chat/Instance do gateway.
 * Tipos para configuracao e resolucao de instancias por webhook token.
 */

/**
 * Provedor de mensageria aceito para uma instancia de chat.
 */
export type ProviderType = 'uazapi' | 'zapi' | 'meta';

/**
 * Representa uma instancia resolvida a partir do token do webhook.
 */
export interface ResolvedInstance {
  /** Identificador da instancia no banco. */
  instance_id: string;
  /** Identificador do tenant dono da instancia. */
  tenant_id: string;
  /** Provedor vinculado a instancia. */
  provider: ProviderType;
  /** Token de instancia para operacoes no provedor. */
  instance_token?: string;
  /** Telefone associado a instancia, quando disponivel. */
  phone?: string;
  /** Configuracoes adicionais da instancia. */
  settings?: Record<string, unknown>;
  /** Status atual da conexao da instancia. */
  status?: string;
}
