/**
 * Representa uma lista de transmissão de mensagens do chat.
 *
 * Contexto: usada no módulo de Listas de Transmissão para enviar mensagens
 * em massa para múltiplos contatos filtrados por critérios específicos.
 */
export interface ChatTransmissionList {
  id: string;
  tenant_id: string;
  name: string;
  /** Mensagem que será enviada aos contatos da lista. */
  message?: string | null;
  /** Critérios de filtragem dos contatos destinatários. */
  filter_criteria?: {
    tags?: string[];
    status?: string;
    company_id?: string;
  } | null;
  /** ID da instância de WhatsApp usada para o envio. */
  instance_id?: string | null;
  /** Status atual da transmissão. */
  status: 'draft' | 'scheduled' | 'running' | 'completed' | 'failed' | 'cancelled';
  /** Data e hora agendada para envio (null = envio imediato). */
  scheduled_at?: string | null;
  /** Data e hora em que o envio foi iniciado. */
  sent_at?: string | null;
  created_at: string;
  updated_at: string;
  /** Metadados adicionais como contagem de entregas realizadas. */
  metadata?: {
    deliveries?: number;
  } | null;
}

/**
 * Payload para criação ou edição de uma lista de transmissão.
 */
export interface ChatTransmissionListPayload {
  name: string;
  message?: string;
  filter_criteria?: {
    tags?: string[];
    status?: string;
    company_id?: string;
  };
  instance_id?: string;
  scheduled_at?: string | null;
  status?: string;
}

/**
 * Resposta paginada da API para listagem de listas de transmissão.
 */
export interface ChatTransmissionListListResponse {
  success: boolean;
  data: {
    data: ChatTransmissionList[];
    current_page?: number;
    last_page?: number;
    per_page?: number;
    total?: number;
  };
}

/**
 * Resposta da API para uma única lista de transmissão.
 */
export interface ChatTransmissionListResponse {
  success: boolean;
  data: ChatTransmissionList;
}

/**
 * Prévia de uma mensagem de transmissão com variáveis substituídas.
 *
 * Contexto: retornada pelo endpoint de pré-visualização antes do envio,
 * mostrando como a mensagem ficará com dados de um contato de amostra.
 */
export interface ChatTransmissionListPreview {
  /** Mensagem original com placeholders de variáveis. */
  original: string;
  /** Mensagem renderizada com variáveis substituídas pelo contato de amostra. */
  preview: string;
  /** Lista de variáveis detectadas na mensagem. */
  vars_detected: string[];
  /** Contato de amostra usado para preencher as variáveis. */
  sample_contact: { name: string; phone: string } | null;
  /** Aviso opcional sobre variáveis sem dados disponíveis. */
  warning?: string;
}
