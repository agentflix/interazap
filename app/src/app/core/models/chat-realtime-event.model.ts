/**
 * Evento de atualização de status de uma mensagem recebido via WebSocket.
 *
 * Contexto: emitido pelo gateway quando o status de entrega/leitura de uma
 * mensagem muda (ex: enviada, entregue, lida, falha).
 */
export interface ChatMessageStatusEvent {
  message_id?: string;
  external_id?: string;
  ticket_id?: string;
  /** Novo status da mensagem. */
  status: 'pending' | 'sent' | 'delivered' | 'read' | 'failed' | 'deleted';
  error_message?: string;
  sent_at?: string;
  delivered_at?: string;
  read_at?: string;
  file_url?: string | null;
  file_name?: string | null;
  mime_type?: string | null;
  file_size?: number | null;
  /** Status da transcrição de mídia associada à mensagem. */
  media_transcription_status?: 'pending' | 'processing' | 'completed' | 'failed' | null;
  media_transcription?: string | null;
}

/**
 * Evento de indicador de digitação/gravação recebido via WebSocket.
 *
 * Contexto: emitido quando o contato está digitando ou gravando áudio.
 */
export interface ChatTypingEvent {
  ticket_id: string;
  user_id?: string;
  contact_id?: string;
  is_typing: boolean;
  /** Tipo de presença ativa. */
  presence?: 'composing' | 'recording' | 'paused';
}

/**
 * Evento de nova mensagem recebida via WebSocket.
 *
 * Contexto: emitido quando uma nova mensagem chega em um ticket ativo.
 */
export interface ChatNewMessageEvent {
  ticket_id: string;
  message: {
    id: string;
    content?: string;
    type?: string;
    direction?: string;
    status?: string;
    created_at?: string;
    [key: string]: unknown;
  };
}

/**
 * Evento de reação a uma mensagem recebido via WebSocket.
 *
 * Contexto: emitido quando um contato adiciona ou remove um emoji de reação.
 */
export interface ChatMessageReactionEvent {
  message_id: string;
  ticket_id: string;
  tenant_id?: string;
  /** Emoji da reação (null quando a reação foi removida). */
  emoji: string | null;
  from_me?: boolean;
  reactions?: { emoji: string; from_me: boolean; timestamp: string }[];
}

/**
 * Evento de edição de uma mensagem recebido via WebSocket.
 *
 * Contexto: emitido quando um contato edita uma mensagem já enviada.
 */
export interface ChatMessageEditEvent {
  message_id: string;
  ticket_id: string;
  tenant_id?: string;
  external_id?: string;
  /** Novo conteúdo da mensagem editada. */
  content: string;
  is_edited: boolean;
  edited_at?: string | null;
}

/**
 * Evento de exclusão de uma mensagem recebido via WebSocket.
 *
 * Contexto: emitido quando um contato exclui uma mensagem já enviada.
 */
export interface ChatMessageDeleteEvent {
  message_id: string;
  ticket_id: string;
  tenant_id?: string;
  status?: 'deleted';
  deleted_at?: string | null;
  deleted_by?: string | null;
}

/**
 * Subtipo de evento dentro de um evento de atividade do chat.
 *
 * Contexto: usado pelo evento `chat.activity` para encapsular múltiplos
 * eventos relacionados em uma única emissão WebSocket.
 */
export type ChatActivitySubeventType =
  | 'msg.received'
  | 'msg.status'
  | 'msg.reaction'
  | 'msg.edit'
  | 'msg.delete'
  | 'ai.processing.started'
  | 'ai.processing.completed'
  | 'ai.processing.failed'
  | 'ai.processing.rejected'
  | 'ai.run.streaming'
  | 'chat.list.updated'
  | 'contact.updated'
  | 'deal.updated'
  | 'negotiation.status.changed'
  | 'ticket.new'
  | 'ticket.updated';

/**
 * Sub-evento individual dentro de um evento de atividade do chat.
 */
export interface ChatActivitySubevent {
  type: ChatActivitySubeventType;
  data: unknown;
}

/**
 * Evento de atividade agregada do chat recebido via WebSocket.
 *
 * Contexto: evento envelope que pode conter múltiplos sub-eventos
 * relacionados a um mesmo ticket ou chat em uma única emissão.
 */
export interface ChatActivityEvent {
  event: 'chat.activity';
  chatId?: string;
  ticketId?: string;
  tenantId?: string;
  timestamp: string;
  subevents: ChatActivitySubevent[];
}

/**
 * Evento de novo ticket criado recebido via WebSocket.
 *
 * Contexto: emitido quando um novo ticket é aberto por um contato.
 */
export interface ChatNewTicketEvent {
  ticket_id: string;
  tenant_id?: string;
  ticket: {
    id: string;
    protocol?: string;
    contact_id?: string;
    remote_jid?: string;
    push_name?: string;
    status?: string;
    [key: string]: unknown;
  };
}

/**
 * Evento de atualização de ticket recebido via WebSocket.
 *
 * Contexto: emitido quando dados de um ticket são atualizados
 * (ex: nova mensagem, mudança de status, transferência).
 */
export interface ChatTicketUpdatedEvent {
  ticket_id: string;
  tenant_id?: string;
  ticket: {
    id: string;
    protocol?: string;
    last_message_at?: string;
    latest_message?: { content?: string; [key: string]: unknown };
    [key: string]: unknown;
  };
}

/**
 * Evento de atualização de sentimento de um ticket recebido via WebSocket.
 *
 * Contexto: emitido quando a IA processa e atualiza o sentimento detectado
 * nas mensagens de um ticket.
 */
export interface ChatTicketSentimentUpdatedEvent {
  ticket_id: string;
  tenant_id?: string;
  sentiment: 'positive' | 'neutral' | 'negative' | 'critical';
  /** Pontuação numérica do sentimento (0 a 1). */
  sentiment_score: number;
}

/**
 * Evento de mudança de status de conexão de uma instância de WhatsApp.
 *
 * Contexto: emitido quando uma instância conecta, desconecta ou gera QR Code.
 */
export interface IntegrationConnectionEvent {
  tenant_id?: string | null;
  instance_id?: string | null;
  token?: string | null;
  status?: string | null;
  connected?: boolean;
  /** QR Code para autenticação do WhatsApp (quando status = 'qr'). */
  qrcode?: string | null;
  /** Código de emparelhamento alternativo ao QR Code. */
  paircode?: string | null;
  raw?: unknown;
}

/**
 * União discriminada de todos os eventos em tempo real do chat.
 *
 * Contexto: tipo usado pelo RealtimeService para tipagem dos eventos
 * recebidos via WebSocket e distribuídos para os componentes do chat.
 */
export type ChatRealtimeEvent =
  | { type: 'status'; payload: ChatMessageStatusEvent }
  | { type: 'new'; payload: ChatNewMessageEvent }
  | { type: 'typing'; payload: ChatTypingEvent }
  | { type: 'reaction'; payload: ChatMessageReactionEvent }
  | { type: 'edit'; payload: ChatMessageEditEvent }
  | { type: 'delete'; payload: ChatMessageDeleteEvent }
  | { type: 'newTicket'; payload: ChatNewTicketEvent };
