/**
 * Modelos de domínio do webchat para o widget de chat público.
 * Estes tipos são utilizados nas páginas e serviços de webchat.
 */

/** Resultado da criação de uma nova sessão de webchat. */
export interface WebChatSessionResponse {
  token: string;
  sessionId: string;
  ticketId: string;
  tenantId: string;
  contactName?: string;
  contactPhone?: string;
  protocol?: string;
}

/** Status público do ticket no ciclo de vida do webchat. */
export type WebChatTicketStatus = 'open' | 'closed';

/** Dados retornados por POST /api/webchat/close. */
export interface WebChatCloseResponse {
  ticketId: string;
  status: WebChatTicketStatus;
  closedAt?: string | null;
}

/** Tipos de mensagem suportados nas requisições do webchat. */
export type WebChatMessageType = 'text' | 'image' | 'video' | 'audio' | 'document';

/** Corpo da requisição de envio de mensagem pelo visitante. */
export interface WebChatMessageRequest {
  token: string;
  content?: string;
  file_url?: string;
  file_name?: string;
  mime_type?: string;
  type?: WebChatMessageType;
}

/** Resposta após upload de arquivo de mídia. */
export interface WebChatMediaUploadResponse {
  url: string;
  file_name: string;
  mime_type: string;
  size: number;
}

/** Resposta após envio de mensagem com sucesso. */
export interface WebChatMessageResponse {
  messageId: string;
}

/** Representa uma única mensagem na conversa do webchat. */
export interface WebChatMessage {
  id: string;
  content: string;
  direction: 'incoming' | 'outgoing';
  source?: 'visitor' | 'ai' | 'agent';
  type: 'text' | 'image' | 'video' | 'file' | 'audio';
  status?: 'pending' | 'sent' | 'delivered' | 'read' | 'failed';
  createdAt: string;
  sessionId: string;
  fileUrl?: string;
  mimeType?: string;
  fileName?: string;
}

/** Dados coletados no formulário de pré-chat. */
export interface PreChatData {
  name: string;
  whatsapp: string;
}

/** Eventos WebSocket emitidos pelo Gateway para o widget. */
export interface WebChatSocketEvents {
  /** Servidor confirma que o visitante entrou na sala da sessão. */
  webchatJoined: { sessionId: string };
  /** Servidor confirma o recebimento de uma mensagem enviada. */
  webchatSent: { messageId: string; tempId?: string };
  /** Resposta da IA ou do atendente recebida. */
  webchatAiResponse: WebChatMessage;
  /** Indicador de digitação. */
  webchatTyping: { isTyping: boolean; source?: 'ai' | 'agent' };
  /** Evento de erro. */
  webchatError: { code: string; message: string };
}

/** Estado da conexão WebSocket do webchat. */
export type WebChatConnectionState = 'disconnected' | 'connecting' | 'connected' | 'error';

/** Estado de validade do formulário de pré-chat. */
export interface PreChatFormState {
  name: string;
  whatsapp: string;
  isValid: boolean;
  errors: {
    name?: string;
    whatsapp?: string;
  };
}

/** Informações públicas do tenant exibidas no widget de webchat. */
export interface WebChatTenantInfo {
  name: string;
}

/** Detalhes de uma sessão de webchat ativa. */
export interface WebChatSessionDetail {
  id: string;
  ticket: { id: string; status: string; protocol?: string } | null;
}
