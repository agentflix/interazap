/**
 * Resposta paginada genérica usada pelas APIs do UaZapi.
 */
export interface Paginated<T> {
  data: T[];
  meta: {
    current_page: number;
    total: number;
    per_page: number;
    last_page: number;
  };
}

/**
 * Status possíveis de uma instância UaZapi.
 *
 * - `connected`: conectada e operacional
 * - `disconnected`: desconectada
 * - `connecting`: tentando conectar
 * - `qr`: aguardando leitura de QR Code
 * - `all`: filtro especial para listar todos os status
 */
export type UazapiInstanceStatus = 'connected' | 'disconnected' | 'connecting' | 'qr' | 'all';

/**
 * Envelope genérico de resposta da API UaZapi.
 *
 * @template T Tipo do dado retornado no campo `data`.
 */
export interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

/**
 * Representa uma instância de WhatsApp gerenciada pelo provedor UaZapi.
 *
 * Contexto: usado no painel administrativo da plataforma (platform/uazapi-instances)
 * para gerenciar as instâncias de WhatsApp disponíveis para os tenants.
 */
export interface UazapiInstance {
  id: string;
  name: string | null;
  /** Nome do sistema interno da instância no UaZapi. */
  system_name: string | null;
  token?: string | null;
  /** Configurações específicas da instância (pares chave-valor). */
  config?: Record<string, string | null> | null;
  /** Status atual de conexão da instância. */
  status?: string | null;
  last_seen_at?: string | null;
  updated_at?: string | null;
  /** Prévia mascarada do token (apenas últimos caracteres). */
  token_preview?: string | null;
  metadata?: Record<string, unknown> | null;
}

/**
 * Filtros para listagem de instâncias UaZapi.
 */
export interface UazapiInstanceFilters {
  search?: string | undefined;
  status?: UazapiInstanceStatus;
  page?: number;
  per_page?: number;
}

/**
 * Payload para criação de uma nova instância UaZapi.
 */
export interface CreateUazapiInstancePayload {
  name: string;
  system_name: string;
  config?: Record<string, string | null> | null;
}

/**
 * Payload para atualização de campos administrativos de uma instância UaZapi.
 */
export interface UpdateAdminFieldsPayload {
  config: Record<string, string | null>;
}

/**
 * Payload para iniciar a conexão de uma instância UaZapi.
 */
export interface ConnectInstancePayload {
  /** Modo de autenticação: por QR Code ou código de emparelhamento. */
  mode: 'qr' | 'pair';
  /** Número de telefone (necessário quando mode = 'pair'). */
  phone?: string | null;
}

/**
 * Resposta da API após iniciar conexão de uma instância UaZapi.
 */
export interface ConnectInstanceResponse {
  instance: UazapiInstance;
  connection: Record<string, unknown>;
}

/**
 * Resposta da API com o status de conexão de uma instância UaZapi.
 */
export interface StatusInstanceResponse {
  instance: UazapiInstance;
  status: Record<string, unknown>;
}

/**
 * Resposta da API após desconectar uma instância UaZapi.
 */
export interface DisconnectInstanceResponse {
  instance: UazapiInstance;
  connection: Record<string, unknown>;
}

/**
 * Resposta da API após atualização da imagem de perfil de uma instância UaZapi.
 */
export interface ProfileImageResponse {
  instance: UazapiInstance;
  response: Record<string, unknown>;
}

/**
 * Resposta da API após alteração do status de presença de uma instância UaZapi.
 */
export interface PresenceResponse {
  instance: UazapiInstance;
  response: Record<string, unknown>;
}
