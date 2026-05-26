/**
 * Tipo de correspondência para padrões de resposta automática.
 *
 * - `exact`: correspondência exata com o texto da mensagem
 * - `contains`: mensagem contém o padrão
 * - `starts_with`: mensagem inicia com o padrão
 * - `ends_with`: mensagem termina com o padrão
 * - `regex`: padrão como expressão regular
 */
export type AutoReplyMatchType = 'exact' | 'contains' | 'starts_with' | 'ends_with' | 'regex';

/**
 * Tipo de ação executada por uma regra de resposta automática.
 *
 * - `send_message`: envia mensagem de texto
 * - `transfer_department`: transfere para um departamento
 * - `transfer_agent`: transfere para um agente específico
 * - `close_ticket`: encerra o ticket
 * - `add_tag`: adiciona uma etiqueta ao contato
 * - `create_task`: cria uma tarefa no CRM
 */
export type AutoReplyActionType =
  | 'send_message'
  | 'transfer_department'
  | 'transfer_agent'
  | 'close_ticket'
  | 'add_tag'
  | 'create_task';

/**
 * Representa uma ação a ser executada por uma regra de resposta automática.
 *
 * Contexto: cada regra pode ter múltiplas ações que são executadas em sequência.
 */
export interface AutoReplyAction extends Record<string, unknown> {
  /** Tipo da ação a executar. */
  type: AutoReplyActionType;
  /** Conteúdo da mensagem (quando type = 'send_message'). */
  message?: string;
  /** Tipo da mensagem enviada (texto, imagem, etc.). */
  message_type?: string;
  /** ID do departamento de destino (quando type = 'transfer_department'). */
  department_id?: string;
}

/**
 * Representa uma regra de resposta automática configurada no sistema.
 *
 * Contexto: usada para configurar automações de atendimento no módulo de Chat.
 * Regras são avaliadas em ordem de prioridade quando uma mensagem é recebida.
 */
export interface AutoReplyRule {
  id: string;
  company_id: string;
  /** ID da instância de WhatsApp à qual a regra se aplica (null = todas). */
  instance_id?: string | null;
  /** ID do departamento ao qual a regra se aplica (null = todos). */
  department_id?: string | null;
  name: string;
  description?: string | null;
  /** Se verdadeiro, esta regra é a mensagem de boas-vindas. */
  is_welcome?: boolean;
  /** Tipo de correspondência usado para avaliar os padrões. */
  match_type: AutoReplyMatchType;
  /** Lista de padrões de texto que disparam a regra. */
  patterns: string[];
  /** Lista de ações executadas quando a regra é acionada. */
  actions: AutoReplyAction[];
  /** Intervalo mínimo em segundos entre disparos da mesma regra para o mesmo contato. */
  cooldown_seconds: number;
  /** Prioridade de avaliação (menor número = maior prioridade). */
  priority: number;
  is_active: boolean;
  /** Se verdadeiro, respeita o horário de atendimento configurado. */
  respect_business_hours: boolean;
  department?: {
    id: string;
    name: string;
  } | null;
  instance?: {
    id: string;
    name?: string | null;
    phone?: string | null;
  } | null;
  created_at: string;
  updated_at: string;
}

/**
 * Payload para criação ou atualização de uma regra de resposta automática.
 *
 * Contexto: enviado para os endpoints POST/PUT de regras de auto-reply.
 */
export interface AutoReplyRulePayload {
  name: string;
  description?: string | null;
  instance_id?: string | null;
  department_id?: string | null;
  is_welcome?: boolean;
  match_type: AutoReplyMatchType;
  patterns: string[];
  actions: AutoReplyAction[];
  cooldown_seconds?: number;
  priority?: number;
  is_active?: boolean;
  respect_business_hours?: boolean;
}

/**
 * Resposta paginada da API para listagem de regras de resposta automática.
 */
export interface AutoReplyRuleListResponse {
  success: boolean;
  data: {
    data: AutoReplyRule[];
    current_page?: number;
    last_page?: number;
    per_page?: number;
    total?: number;
  };
}

/**
 * Resposta da API para uma única regra de resposta automática.
 */
export interface AutoReplyRuleResponse {
  success: boolean;
  data: AutoReplyRule;
}

/**
 * Resposta da API para validação de disponibilidade de palavra-chave.
 */
export interface AutoReplyKeywordValidationResponse {
  success: boolean;
  data: {
    /** Indica se o padrão/palavra-chave está disponível (não em uso por outra regra). */
    available: boolean;
  };
}
