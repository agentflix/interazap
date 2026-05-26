/**
 * Status do template Meta WhatsApp.
 * Mantemos os valores em inglês conforme o backend; a tradução fica na camada visual.
 */
export type ChatMessageTemplateStatus = 'approved' | 'pending' | 'rejected' | 'paused' | 'disabled';

/**
 * Provedor do template: `local` para templates internos, `meta` para WhatsApp Business.
 */
export type ChatMessageTemplateProvider = 'local' | 'meta';

/**
 * Categoria do template conforme classificação da Meta.
 *
 * - `MARKETING`: campanhas e promoções
 * - `UTILITY`: notificações transacionais
 * - `AUTHENTICATION`: códigos de verificação
 */
export type ChatMessageTemplateCategory = 'MARKETING' | 'UTILITY' | 'AUTHENTICATION';

/**
 * Tipos de componente de um template Meta WhatsApp.
 */
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
 *
 * Contexto: usado no módulo de Templates Meta para listar, criar e sincronizar
 * templates de mensagem com a API da Meta (WhatsApp Business).
 */
export interface ChatMessageTemplate {
  id: string;
  name: string;
  /** Atalho de texto curto para uso rápido no chat. */
  shortcut: string | null;
  /** Conteúdo textual do template (null para templates Meta com componentes). */
  content: string | null;
  category: ChatMessageTemplateCategory | string;
  is_active: boolean;
  provider: ChatMessageTemplateProvider;
  /** ID da instância de WhatsApp associada ao template. */
  chat_instance_id: string | null;
  /** ID externo do template na plataforma Meta. */
  external_id: string | null;
  /** Idioma do template (ex: pt_BR, en_US). */
  language: string;
  status: ChatMessageTemplateStatus;
  /** Motivo de rejeição informado pela Meta (quando status = 'rejected'). */
  rejected_reason: string | null;
  /** Componentes estruturados do template (header, body, footer, buttons). */
  components: ChatMessageTemplateComponent[];
  /** Data da última sincronização com a API da Meta. */
  last_synced_at: string | null;
  created_at?: string | null;
  updated_at?: string | null;
}

/**
 * Filtros aceitos pelo endpoint de listagem de templates de mensagem.
 */
export interface ChatMessageTemplateFilters {
  search?: string;
  status?: ChatMessageTemplateStatus | '';
  chat_instance_id?: string | null;
  provider?: ChatMessageTemplateProvider;
  page?: number;
  per_page?: number;
}

/**
 * Payload de criação ou edição de um template de mensagem.
 */
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

/**
 * Resposta do endpoint de sincronização de templates com a Meta.
 */
export interface ChatMessageTemplateSyncResponse {
  /** Número de templates sincronizados. */
  count: number;
}
