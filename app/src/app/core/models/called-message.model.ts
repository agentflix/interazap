/**
 * Resumo do usuário remetente de uma mensagem de atendimento.
 */
export interface CalledMessageUser {
  id: string | number;
  name: string;
}

/**
 * Telefone de um contato dentro de uma mensagem do tipo contato.
 */
export interface CalledMessageContactPhone {
  number?: string;
  type?: string;
}

/**
 * Entrada de contato dentro de metadados de uma mensagem do tipo contato.
 */
export interface CalledMessageContactEntry {
  fullName?: string;
  phoneNumber?: string;
  organization?: string;
  email?: string;
  phones?: CalledMessageContactPhone[];
}

/**
 * Metadados de uma mensagem do tipo contato compartilhado via WhatsApp.
 */
export interface CalledMessageContactMetadata {
  fullName?: string;
  phoneNumber?: string;
  organization?: string;
  email?: string;
  url?: string;
  /** Lista de contatos incluídos na mensagem. */
  contacts?: CalledMessageContactEntry[];
}

/**
 * Metadados de uma mensagem do tipo localização compartilhada via WhatsApp.
 */
export interface CalledMessageLocationMetadata {
  latitude?: number;
  longitude?: number;
  lat?: number;
  lng?: number;
  address?: string;
  name?: string;
}

/**
 * Metadados genéricos de uma mensagem (contato, localização, etc.).
 *
 * Contexto: campo livre para dados adicionais dependentes do tipo de mensagem.
 */
export interface CalledMessageMetadata {
  contact?: CalledMessageContactMetadata;
  location?: CalledMessageLocationMetadata;
  [key: string]: unknown;
}

/**
 * Representa uma mensagem citada (reply) dentro de uma mensagem de atendimento.
 */
export interface CalledMessageQuotedMessage {
  id?: string | number | null;
  external_id?: string | null;
  content?: string | null;
  type?: string | null;
  direction?: 'incoming' | 'outgoing' | null;
  sender_name?: string | null;
  file_name?: string | null;
  mime_type?: string | null;
  is_edited?: boolean;
  edited_at?: string | null;
}

/**
 * Representa uma mensagem individual de um ticket de atendimento.
 *
 * Contexto: usado no módulo de Chat para exibir o histórico de mensagens
 * de um ticket (chamado). Tanto mensagens recebidas quanto enviadas.
 */
export interface CalledMessage {
  id: string | number;
  called_id?: string | number;
  ticket_id?: string | number;
  /** ID do usuário que enviou a mensagem (null se enviado pelo contato). */
  user_id?: string | number | null;
  /** ID do contato que enviou a mensagem (null se enviado pelo agente). */
  contact_id?: string | number | null;
  /** ID externo da mensagem no WhatsApp. */
  external_id?: string | null;
  content?: string | null;
  /** Tipo da mensagem (text, image, audio, video, document, etc.). */
  type?: string;
  /** Direção da mensagem: recebida (incoming) ou enviada (outgoing). */
  direction?: 'incoming' | 'outgoing';
  /** Status de entrega da mensagem. */
  status?: string | null;
  is_deleted?: boolean;
  /** URL do arquivo de mídia anexado. */
  file_url?: string | null;
  file_name?: string | null;
  mime_type?: string | null;
  file_size?: number | null;
  sent_at?: string | null;
  delivered_at?: string | null;
  read_at?: string | null;
  deleted_at?: string | null;
  deleted_by?: string | null;
  /** Reações emoji feitas à mensagem. */
  reactions?: { emoji: string; from_me: boolean; timestamp: string }[] | null;
  is_edited?: boolean;
  edited_at?: string | null;
  /** Histórico de edições do conteúdo da mensagem. */
  edit_history?: { content: string; edited_at: string }[] | null;
  /** Transcrição automática de áudio/vídeo. */
  media_transcription?: string | null;
  /** Status da transcrição de mídia. */
  media_transcription_status?: 'pending' | 'processing' | 'completed' | 'failed' | null;
  media_transcription_provider?: string | null;
  created_at?: string | null;
  metadata?: CalledMessageMetadata | null;
  /** Mensagem citada (reply). */
  quoted_message?: CalledMessageQuotedMessage | null;
  user?: CalledMessageUser | null;
}

/**
 * Resposta paginada da API para listagem de mensagens de um ticket.
 */
export interface CalledMessageListResponse {
  data: {
    messages: CalledMessage[];
    meta: PaginationMeta;
  };
}

/**
 * Resposta da API após enviar uma mensagem em um ticket.
 */
export interface CalledMessageSendResponse {
  data: CalledMessage;
}

/**
 * Metadados de paginação retornados nas listagens de mensagens.
 */
export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  /** Indica se há mais mensagens além da página atual. */
  has_more: boolean;
}

/**
 * Opções para busca paginada de mensagens de um ticket.
 */
export interface CalledMessageListOptions {
  page?: number;
  limit?: number;
  /** Busca mensagens a partir deste timestamp ISO. */
  since?: string;
}

/**
 * Resposta da API com dados de mídia de uma mensagem (URL pré-assinada).
 */
export interface CalledMessageMediaResponse {
  data: {
    url: string;
    file_name?: string | null;
    mime_type?: string | null;
    file_size?: number | null;
    size?: number | null;
    metadata?: Record<string, unknown> | null;
  };
}
