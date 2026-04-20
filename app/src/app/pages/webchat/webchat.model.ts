/**
 * WebChat domain models for the public-facing chat widget.
 * These types are used across the webchat pages and services.
 */

/** Result of creating a new webchat session */
export interface WebChatSessionResponse {
  token: string;
  sessionId: string;
  ticketId: string;
  tenantId: string;
}

/** Incoming message from the visitor */
export interface WebChatMessageRequest {
  token: string;
  content: string;
}

/** Response after sending a message */
export interface WebChatMessageResponse {
  messageId: string;
}

/** A single message in the webchat conversation */
export interface WebChatMessage {
  id: string;
  content: string;
  direction: 'incoming' | 'outgoing';
  source?: 'visitor' | 'ai' | 'agent';
  type: 'text' | 'image' | 'video' | 'file' | 'audio';
  status?: 'pending' | 'sent' | 'delivered' | 'read' | 'failed';
  createdAt: string;
  sessionId: string;
}

/** Data collected during pre-chat form */
export interface PreChatData {
  name: string;
  whatsapp: string;
}

/** WebSocket events from the Gateway */
export interface WebChatSocketEvents {
  /** Server confirms the visitor joined the session room */
  webchatJoined: { sessionId: string };
  /** Server acknowledges a sent message */
  webchatSent: { messageId: string; tempId?: string };
  /** AI or agent response received */
  webchatAiResponse: WebChatMessage;
  /** Typing indicator */
  webchatTyping: { isTyping: boolean; source?: 'ai' | 'agent' };
  /** Error event */
  webchatError: { code: string; message: string };
}

/** Connection state of the WebSocket */
export type WebChatConnectionState = 'disconnected' | 'connecting' | 'connected' | 'error';

/** Form validity state for pre-chat */
export interface PreChatFormState {
  name: string;
  whatsapp: string;
  isValid: boolean;
  errors: {
    name?: string;
    whatsapp?: string;
  };
}
