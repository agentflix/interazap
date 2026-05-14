/**
 * Status do template Meta WhatsApp.
 * Mantemos os valores em inglês conforme o backend; a tradução fica na camada visual.
 */
export type ChatMessageTemplateStatus = 'approved' | 'pending' | 'rejected' | 'paused' | 'disabled';

/** Provider — `local` para templates internos, `meta` para WhatsApp Business. */
export type ChatMessageTemplateProvider = 'local' | 'meta';

/** Categoria conforme classificação Meta. */
export type ChatMessageTemplateCategory = 'MARKETING' | 'UTILITY' | 'AUTHENTICATION';

/** Tipos de componente de um template Meta. */
export type ChatMessageTemplateComponentType = 'HEADER' | 'BODY' | 'FOOTER' | 'BUTTONS';

/**
 * Estrutura genérica de componente do template Meta.
 * Mantemos o JSON cru proveniente do backend.
 */
export interface ChatMessageTemplateComponent {
  type: ChatMessageTemplateComponentType;
  format?: 'TEXT' | 'IMAGE' | 'VIDEO' | 'DOCUMENT';
  text?: string;
  example?: {
    body_text?: string[][];
    header_text?: string[];
    header_handle?: string[];
  };
  buttons?: {
    type: 'QUICK_REPLY' | 'URL' | 'PHONE_NUMBER';
    text: string;
    url?: string;
    phone_number?: string;
  }[];
}

/**
 * Representação completa do recurso `ChatMessageTemplate` exposto pela API
 * (`api/src/Domain/Chat/Http/Resources/ChatMessageTemplateResource.php`).
 */
export interface ChatMessageTemplate {
  id: string;
  name: string;
  shortcut: string | null;
  content: string | null;
  category: ChatMessageTemplateCategory | string;
  is_active: boolean;
  provider: ChatMessageTemplateProvider;
  chat_instance_id: string | null;
  external_id: string | null;
  language: string;
  status: ChatMessageTemplateStatus;
  rejected_reason: string | null;
  components: ChatMessageTemplateComponent[];
  last_synced_at: string | null;
  created_at?: string | null;
  updated_at?: string | null;
}

/** Filtros aceitos pelo endpoint de listagem. */
export interface ChatMessageTemplateFilters {
  search?: string;
  status?: ChatMessageTemplateStatus | '';
  chat_instance_id?: string | null;
  provider?: ChatMessageTemplateProvider;
  page?: number;
  per_page?: number;
}

/** Payload de criação/edição. */
export interface ChatMessageTemplatePayload {
  name?: string;
  language?: string;
  category?: ChatMessageTemplateCategory | string;
  chat_instance_id?: string | null;
  components?: ChatMessageTemplateComponent[];
  shortcut?: string | null;
  is_active?: boolean;
  provider?: ChatMessageTemplateProvider;
}

/** Resposta do endpoint de sincronização. */
export interface ChatMessageTemplateSyncResponse {
  count: number;
}
