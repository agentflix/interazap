/**
 * Presets disponíveis para configuração de Agente de IA via API.
 *
 * ⚠️ IMPORTANTE: Este tipo representa APENAS os valores de preset
 * suportados pela API para carregar conjuntos padrão de tools.
 * NÃO é uma permissão de autorização em runtime.
 * A permissão final do agent é determinada exclusivamente pelas
 * tools vinculadas (AiAgentToolLink) e suas configurações no backend.
 */
export type AiAgentPreset =
  | 'sales_qualifier'
  | 'support_l1'
  | 'cs_retention'
  | 'post_sales'
  | 'appointment'
  | 'general'
  | 'custom';

/**
 * @deprecated Use AiAgentPreset. Mantido apenas para compatibilidade temporária.
 */
export type AiAgentRole = AiAgentPreset;

/**
 * Canais nos quais um agente pode operar.
 */
export type AiAgentChannel = 'whatsapp' | 'webchat' | 'email' | 'internal';

/**
 * Modo de resposta por voz do agente.
 */
export type AiVoiceResponseMode = 'text' | 'audio' | 'mixed';

/**
 * Modelo de Agente de IA para configuração de comportamentos de bot (V2).
 */
export interface AiAgent {
  id: string;
  name: string;
  description?: string | null;
  type: AiAgentType;
  model_id?: string | null;
  classifier_model?: string | null;
  system_prompt?: string | null;
  max_tokens: number;
  temperature: number;
  top_p: number;
  token_budget_per_run?: number | null;
  token_budget_daily?: number | null;
  fallback_message?: string | null;
  is_active: boolean;
  parent_agent_id?: string | null;
  metadata?: Record<string, unknown> | null;
  channels?: AiAgentChannel[];
  tools?: AiAgentToolLink[];
  voice_response_mode?: AiVoiceResponseMode;
  stt_model?: string | null;
  stt_language?: string | null;
  tts_model?: string | null;
  tts_voice?: string | null;
  tts_speed?: number | null;
  created_at?: string;
  updated_at?: string;
}

/**
 * Payload para criação ou atualização de um Agente de IA (V2).
 *
 * ⚠️ Este payload NÃO contém campo `role` de autorização runtime.
 * A autorização do agent é determinada pelas tools vinculadas (tools[])
 * e validada pelo backend. O campo `role`/preset serve apenas para
 * carregar configurações padrão de tools, não para controle de acesso.
 */
export interface AiAgentPayload {
  name: string;
  description?: string | null;
  type?: AiAgentType;
  model_id?: string | null;
  classifier_model?: string | null;
  system_prompt?: string | null;
  max_tokens?: number;
  temperature?: number;
  top_p?: number;
  token_budget_per_run?: number | null;
  token_budget_daily?: number | null;
  fallback_message?: string | null;
  is_active?: boolean;
  parent_agent_id?: string | null;
  channels?: AiAgentChannel[];
  voice_response_mode?: AiVoiceResponseMode;
  stt_model?: string | null;
  stt_language?: string | null;
  tts_model?: string | null;
  tts_voice?: string | null;
  tts_speed?: number | null;
}

/**
 * Tipo do Agente de IA (compatibilidade legado + novos papéis).
 */
export type AiAgentType = 'sales' | 'support' | 'qualifier' | 'general';

/**
 * Arquivo principal de configuração do agente.
 */
export interface AiAgentFile {
  slug: string;
  content: string;
  size_bytes: number;
  size_formatted: string;
  updated_by?: string | null;
  updated_at?: string;
}

/**
 * Item do catálogo de ferramentas disponíveis para agentes.
 *
 * ⚠️ O campo `presets` indica em quais presets esta tool está incluída
 * por padrão. NÃO controla autorização — a permissão efetiva vem das
 * tools vinculadas ao agent (AiAgentToolLink) validadas pelo backend.
 */
export interface AiToolCatalogItem {
  id: string;
  name: string;
  description: string;
  category: AiToolCategory;
  execution_mode: 'local' | 'remote';
  requires_approval: boolean;
  is_active: boolean;
  /** Presets em que esta tool aparece por padrão. Não é controle de acesso. */
  presets: AiAgentPreset[];
  locked?: boolean;
  locked_reason?: string | null;
}

/**
 * Categoria de ferramenta do agente.
 */
export type AiToolCategory =
  | 'crm'
  | 'sales'
  | 'chat'
  | 'knowledge'
  | 'productivity'
  | 'notification'
  | 'meta';

/**
 * Vínculo entre agente e ferramenta (tabela pivot).
 */
export interface AiAgentToolLink {
  tool_id: string;
  tool_name: string;
}

/**
 * Definição de habilidade (skill) do agente.
 */
export interface AiAgentSkill {
  id: string;
  agent_id: string;
  skill_type?: AiSkillType;
  name: string;
  description?: string | null;
  config: Record<string, unknown>;
  is_active: boolean;
  priority?: number;
  metadata?: AiAgentSkillMetadata | null;
  created_at?: string;
  updated_at?: string;
}

/**
 * Metadados de skill persistidos pelo backend.
 */
export interface AiAgentSkillMetadata {
  type?: AiSkillType;
  priority?: number;
}

/**
 * Payload para criação ou atualização de skill do agente.
 */
export interface AiAgentSkillPayload {
  name: string;
  description?: string | null;
  config?: Record<string, unknown>;
  is_active?: boolean;
  metadata: {
    type: AiSkillType;
    priority: number;
    prompt_append?: string;
  };
}

/**
 * Tipos de skill disponíveis para o agente.
 */
export type AiSkillType =
  | 'classification'
  | 'extraction'
  | 'routing'
  | 'validation'
  | 'summarization'
  | 'custom';

/**
 * Configuração de trigger do agente.
 */
export interface AiAgentTrigger {
  id: string;
  agent_id: string;
  name?: string | null;
  type: AiTriggerType;
  config: AiAgentTriggerConfig;
  status: 'active' | 'inactive';
  last_run_at?: string | null;
  created_at?: string;
  updated_at?: string;
}

/**
 * Configuração de trigger persistida no backend como JSON.
 */
export interface AiAgentTriggerConfig {
  name?: string;
  cron_expression?: string;
  webhook_url?: string;
  event_name?: string;
  [key: string]: string | undefined;
}

/**
 * Payload para criação ou atualização de trigger do agente.
 */
export interface AiAgentTriggerPayload {
  type: AiTriggerType;
  config: AiAgentTriggerConfig;
  status?: 'active' | 'inactive';
}

/**
 * Tipos de trigger disponíveis para o agente.
 */
export type AiTriggerType = 'cron' | 'webhook' | 'event';

/**
 * Configuração de voz do agente (STT/TTS e modo de resposta).
 */
export interface AiAgentVoiceConfig {
  voice_response_mode: AiVoiceResponseMode;
  stt_model: string | null;
  stt_language: string | null;
  tts_model: string | null;
  tts_voice: string | null;
  tts_speed: number | null;
}

/**
 * Evento de streaming para renderização progressiva de texto.
 */
export interface AiStreamingEvent {
  run_id: string;
  chunk: string;
  chunk_index: number;
  is_final: boolean;
  accumulated_text?: string;
}

/**
 * Configuração de canal do agente.
 */
export interface AiAgentChannelConfig {
  id: string;
  agent_id: string;
  channel: AiAgentChannel;
  is_active: boolean;
  config?: Record<string, unknown>;
  created_at?: string;
  updated_at?: string;
}

/**
 * Roteiro de execução do Autopilot (sequência de passos automatizados).
 */
export interface AutopilotPlaybook {
  id: string;
  name: string;
  description?: string | null;
  version: number;
  steps?: Record<string, unknown> | null;
  metadata?: Record<string, unknown> | null;
  is_active: boolean;
  created_at?: string;
  updated_at?: string;
}

/** Payload para criação ou atualização de um roteiro do Autopilot. */
export interface AutopilotPlaybookPayload {
  name: string;
  description?: string | null;
  version?: number;
  steps?: Record<string, unknown>;
  metadata?: Record<string, unknown>;
  is_active?: boolean;
}

/**
 * Execução de um roteiro do Autopilot com seu status e resultado.
 */
export interface AutopilotRun {
  id: string;
  playbook_id: string;
  status: string;
  playbook_version: number;
  input_context?: Record<string, unknown> | null;
  output?: Record<string, unknown> | null;
  started_at?: string;
  completed_at?: string;
  created_at?: string;
}

/** Payload para iniciar uma execução do Autopilot. */
export interface AutopilotRunPayload {
  context?: Record<string, unknown>;
}

/**
 * Payload base para eventos realtime de execução de IA.
 */
export interface AiRunEvent {
  run_id: string;
  tenant_id: string;
  status: string;
  message?: string;
}

/**
 * Payload de evento realtime para etapa de chamada de ferramenta.
 */
export interface AiRunToolCallEvent extends AiRunEvent {
  tool_name: string;
  tool_args: Record<string, unknown>;
  iteration: number;
}

/**
 * Payload de evento realtime para etapa de resultado de ferramenta.
 */
export interface AiRunToolResultEvent extends AiRunEvent {
  tool_name: string;
  result: Record<string, unknown>;
  success: boolean;
  iteration: number;
}

/**
 * Payload de evento realtime emitido quando a execução é concluída.
 */
export interface AiRunCompletedEvent extends AiRunEvent {
  output: {
    response?: string;
    error?: string | null;
    blocked?: boolean;
    raw?: {
      model?: string;
      prompt_tokens?: number;
      completion_tokens?: number;
      total_tokens?: number;
      tool_iterations?: number;
    };
  };
}

/**
 * Entrada da Base de Conhecimento da IA.
 */
export interface AiKnowledge {
  id: string;
  name: string;
  original_filename: string;
  file_type: 'txt' | 'csv' | 'markdown' | 'json' | 'pdf' | 'url';
  embedding_status: 'pending' | 'processing' | 'ready' | 'failed';
  file_size_bytes: number;
  file_size_formatted: string;
  version: number;
  error_message?: string | null;
  is_ready?: boolean;
  is_processing?: boolean;
  is_failed?: boolean;
  can_reprocess?: boolean;
  metadata?: Record<string, unknown> | null;

  // Compatibility aliases used by current screens
  title: string;
  content_type: 'text' | 'pdf' | 'url' | 'csv';
  status: 'pending' | 'processing' | 'indexed' | 'failed';
  chunk_count: number;
  token_count: number;
  file_path?: string | null;
  source_url?: string | null;
  created_at?: string;
  updated_at?: string;
}

/** Payload para criação de uma nova entrada na base de conhecimento. */
export interface AiKnowledgePayload {
  title: string;
  content_type: 'text' | 'pdf' | 'url' | 'csv';
  content?: string;
  source_url?: string;
  file?: File;
}

/**
 * Estatísticas da base de conhecimento (armazenamento, documentos por status).
 */
export interface KnowledgeStats {
  document_count: number;
  total_storage_bytes: number;
  storage_limit_bytes: number;
  storage_used_percent: number;
  storage_formatted: string;
  storage_limit_formatted: string;
  total_chunks: number;
  documents_ready: number;
  documents_processing: number;
  documents_pending: number;
  documents_failed: number;
}

/**
 * Item de resultado de busca semântica na base de conhecimento.
 */
export interface KnowledgeSearchResult {
  chunk_id: string;
  document_id: string;
  document_name: string;
  content: string;
  chunk_index: number;
  score: number;
  relevance_percent: number;
}

/**
 * Modos de busca disponíveis para recuperação RAG.
 */
export type KnowledgeSearchMode = 'vector' | 'hybrid';

/**
 * Fragmento (chunk) de um documento da base de conhecimento.
 */
export interface KnowledgeChunk {
  id: string;
  document_id: string;
  chunk_index: number;
  content: string;
  token_count: number;
  created_at?: string;
}

/**
 * Template de Prompt de IA configurável por agente.
 */
export interface AiPrompt {
  id: string;
  agent_id: string;
  name: string;
  template: string;
  variables: string[];
  category?: string | null;
  is_active: boolean;
  created_at?: string;
  updated_at?: string;
}

/** Payload para criação ou atualização de um template de prompt. */
export interface AiPromptPayload {
  agent_id: string;
  name: string;
  template: string;
  variables?: string[];
  category?: string | null;
  is_active?: boolean;
}

/**
 * Resumo de uso da IA no período atual (tokens, custos, tendências).
 */
export interface UsageSummary {
  period_start: string;
  period_end: string;
  total_requests: number;
  total_input_tokens: number;
  total_output_tokens: number;
  total_tokens: number;
  total_cost: number;
  formatted_cost: string;
  avg_latency_ms: number;
  cost_change_percent: number;
  cost_trend: 'up' | 'down' | 'stable';
}

/**
 * Breakdown de uso diário da IA (tokens e custos por dia).
 */
export interface UsageDaily {
  date: string;
  requests: number;
  input_tokens: number;
  output_tokens: number;
  tokens: number;
  cost: number;
  formatted_cost: string;
}

/**
 * Estatísticas de uso por agente (requisições, tokens e custos).
 */
export interface UsageAgent {
  agent_name: string;
  total_requests: number;
  total_tokens: number;
  total_cost: number;
  formatted_cost: string;
  avg_cost_per_request: string;
}

/**
 * Histórico mensal de uso da IA.
 */
export interface UsageMonthly {
  month: string;
  requests: number;
  tokens: number;
  cost: number;
  formatted_cost: string;
}

/**
 * Status do orçamento de tokens (diário e por execução).
 */
export interface UsageBudgetStatus {
  daily_budget: number | null;
  daily_used: number;
  daily_remaining: number;
  daily_percent: number;
  per_run_budget: number | null;
  avg_tokens_per_run: number;
}

/**
 * Métricas de uso de voz (STT e TTS) com custos agregados.
 */
export interface UsageVoice {
  stt_requests: number;
  stt_minutes: number;
  stt_cost: number;
  stt_formatted_cost: string;
  tts_requests: number;
  tts_characters: number;
  tts_cost: number;
  tts_formatted_cost: string;
  total_voice_cost: number;
  total_voice_formatted_cost: string;
}

/**
 * Notificação de IA para vendedores (alertas e recomendações).
 */
export interface AiNotification {
  id: string;
  message: string;
  reason: string;
  reason_label: string;
  channel: string;
  priority: 'low' | 'medium' | 'high';
  is_read: boolean;
  metadata?: Record<string, unknown> | null;
  delivered_at?: string | null;
  created_at: string;
}

/**
 * Precificação de modelo de IA (custo por 1k tokens de entrada e saída).
 */
export interface AiModelPricing {
  id: string;
  model_name: string;
  provider: string;
  input_price_per_1k: number;
  output_price_per_1k: number;
  is_active: boolean;
  created_at?: string;
  updated_at?: string;
}

/**
 * Configuração do prompt mestre para governança global da IA.
 */
export interface MasterPrompt {
  id: string;
  name: string;
  content: string;
  version: number;
  is_active: boolean;
  created_at?: string;
  updated_at?: string;
}

/**
 * Payload para criação ou atualização de um prompt mestre.
 */
export interface MasterPromptPayload {
  name: string;
  content: string;
  is_active?: boolean;
}

/**
 * Configuração de prompt por segmento de negócio (vertical).
 */
export interface SegmentPrompt {
  id: string;
  master_id: string | null;
  code: string;
  name: string;
  description: string | null;
  content: string;
  is_active: boolean;
  is_general?: boolean;
  deleted_at?: string | null;
  master?: MasterPrompt;
  created_at?: string;
  updated_at?: string;
}

/**
 * Payload para criação ou atualização de um prompt de segmento.
 */
export interface SegmentPromptPayload {
  master_id: string;
  code: string;
  name: string;
  description?: string;
  content: string;
  is_active?: boolean;
}

/**
 * Configuração de prompt por plano do tenant.
 */
export interface PlanPrompt {
  id: string;
  plan_id: string;
  content: string;
  is_active: boolean;
  plan?: {
    id?: string;
    name: string;
    slug: string;
  };
  created_at?: string;
  updated_at?: string;
}

/**
 * Payload para atualização da configuração de prompt do plano.
 */
export interface PlanPromptPayload {
  content: string;
  is_active?: boolean;
}

/**
 * Prompt do tenant em quarentena aguardando revisão manual.
 */
export interface QuarantinedPrompt {
  id: string;
  tenant_id: string;
  content: string;
  status: string;
  original_content: string | null;
  quarantine_reason: string | null;
  validation_status: string;
  validation_status_label?: string;
  tenant?: {
    id: string;
    name: string;
  };
  created_at?: string;
  updated_at?: string;
}

/**
 * Payload para rejeitar um prompt em quarentena.
 */
export interface RejectQuarantinePayload {
  reason: string;
}
