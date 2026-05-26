/**
 * Status possíveis de uma instância de WhatsApp.
 *
 * - `connected`: instância conectada e operacional
 * - `connecting`: tentando estabelecer conexão
 * - `disconnected`: desconectada do WhatsApp
 * - `failed`: falha na conexão
 * - `qr`: aguardando leitura de QR Code
 * - `pairing`: aguardando confirmação de emparelhamento por código
 */
export type InstanceStatus =
  | 'connected'
  | 'connecting'
  | 'disconnected'
  | 'failed'
  | 'qr'
  | 'pairing';

/**
 * Representa uma instância de canal (WhatsApp) da empresa.
 *
 * Contexto: cada instância corresponde a um número de WhatsApp conectado
 * ao InteraZap. Usada em tickets, auto-respostas e transmissões.
 */
export interface Instance {
  id: string | number;
  name?: string | null;
  phone?: string | null;
  /** Provedor de integração (ex: uazapi, evolution). */
  provider?: string | null;
  status?: InstanceStatus | null;
  connection_status?: InstanceStatus | string | null;
  is_active?: boolean;
  is_connected?: boolean;
  company_id?: string | number;
  integration_id?: string | number;
  /** Configurações específicas da instância (varia por provedor). */
  settings?: Record<string, unknown> | null;
  /** Token de autenticação da instância. */
  token?: string | null;
  created_at?: string;
  updated_at?: string;
}

/**
 * Filtros para listagem de instâncias de canais.
 */
export interface InstanceFilters {
  search?: string;
  integration_id?: string | number;
  status?: InstanceStatus;
  is_active?: boolean;
  per_page?: number;
  page?: number;
}

/**
 * Resposta paginada da API para listagem de instâncias.
 */
export interface InstanceListResponse {
  data: Instance[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}
