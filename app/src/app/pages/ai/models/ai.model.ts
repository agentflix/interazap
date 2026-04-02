/**
 * AI Agent roles available for configuration.
 */
export type AiAgentRole =
  | 'sales_qualifier'
  | 'support_l1'
  | 'cs_retention'
  | 'post_sales'
  | 'appointment'
  | 'finance'
  | 'routing'
  | 'general'
  | 'custom';

/**
 * Channels where an agent can operate.
 */
export type AiAgentChannel = 'whatsapp' | 'webchat' | 'email' | 'internal';

/**
 * Voice response mode for an agent.
 */
export type AiVoiceResponseMode = 'text' | 'audio' | 'mixed';

/**
 * AI Agent model for configuring bot behaviors (V2).
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
 * Payload for creating/updating an AI agent (V2).
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
 * AI Agent Type (legacy compat + new roles).
 */
export type AiAgentType = 'sales' | 'support' | 'qualifier' | 'general';

/**
 * Agent file (core configuration file).
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
 * Tool catalog item.
 */
export interface AiToolCatalogItem {
  id: string;
  name: string;
  description: string;
  category: AiToolCategory;
  execution_mode: 'local' | 'remote';
  requires_approval: boolean;
  is_active: boolean;
  permissions: AiAgentRole[];
  locked?: boolean;
  locked_reason?: string | null;
}

/**
 * Tool category enum.
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
 * Link between agent and tool (pivot).
 */
export interface AiAgentToolLink {
  tool_id: string;
  tool_name: string;
}

/**
 * Agent skill definition.
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
 * Skill metadata persisted by backend.
 */
export interface AiAgentSkillMetadata {
  type?: AiSkillType;
  priority?: number;
}

/**
 * Payload for creating/updating an agent skill.
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
 * Skill type enum.
 */
export type AiSkillType =
  | 'classification'
  | 'extraction'
  | 'routing'
  | 'validation'
  | 'summarization'
  | 'custom';

/**
 * Agent trigger configuration.
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
 * Trigger configuration persisted in backend as JSON.
 */
export interface AiAgentTriggerConfig {
  name?: string;
  cron_expression?: string;
  webhook_url?: string;
  event_name?: string;
  [key: string]: string | undefined;
}

/**
 * Payload for creating/updating an agent trigger.
 */
export interface AiAgentTriggerPayload {
  type: AiTriggerType;
  config: AiAgentTriggerConfig;
  status?: 'active' | 'inactive';
}

/**
 * Trigger type enum.
 */
export type AiTriggerType = 'cron' | 'webhook' | 'event';

/**
 * Voice configuration for an agent.
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
 * Streaming event for progressive text rendering.
 */
export interface AiStreamingEvent {
  run_id: string;
  chunk: string;
  chunk_index: number;
  is_final: boolean;
  accumulated_text?: string;
}

/**
 * Agent channel configuration.
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
 * Autopilot Playbook
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

export interface AutopilotPlaybookPayload {
  name: string;
  description?: string | null;
  version?: number;
  steps?: Record<string, unknown>;
  metadata?: Record<string, unknown>;
  is_active?: boolean;
}

/**
 * Autopilot Run (Execution)
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

export interface AutopilotRunPayload {
  context?: Record<string, unknown>;
}

/**
 * Base event payload for AI run realtime updates.
 */
export interface AiRunEvent {
  run_id: string;
  tenant_id: string;
  status: string;
  message?: string;
}

/**
 * Realtime event payload for tool call step.
 */
export interface AiRunToolCallEvent extends AiRunEvent {
  tool_name: string;
  tool_args: Record<string, unknown>;
  iteration: number;
}

/**
 * Realtime event payload for tool result step.
 */
export interface AiRunToolResultEvent extends AiRunEvent {
  tool_name: string;
  result: Record<string, unknown>;
  success: boolean;
  iteration: number;
}

/**
 * Realtime event payload emitted when run completes.
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
 * AI Knowledge Base Entry
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

export interface AiKnowledgePayload {
  title: string;
  content_type: 'text' | 'pdf' | 'url' | 'csv';
  content?: string;
  source_url?: string;
  file?: File;
}

/**
 * Knowledge base statistics payload.
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
 * Semantic search result item.
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
 * Available search modes for RAG retrieval.
 */
export type KnowledgeSearchMode = 'vector' | 'hybrid';

/**
 * Chunk item from a knowledge document detail.
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
 * AI Prompt Template
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

export interface AiPromptPayload {
  agent_id: string;
  name: string;
  template: string;
  variables?: string[];
  category?: string | null;
  is_active?: boolean;
}

/**
 * AI Usage Summary
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
 * Daily Usage Breakdown
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
 * Agent Usage Stats
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
 * Monthly Usage History
 */
export interface UsageMonthly {
  month: string;
  requests: number;
  tokens: number;
  cost: number;
  formatted_cost: string;
}

/**
 * Budget status for token budgets.
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
 * Voice usage metrics.
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
 * AI Notification for sellers
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
 * AI Model Pricing
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
 * Master prompt configuration for global AI governance.
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
 * Payload used to create or update a master prompt.
 */
export interface MasterPromptPayload {
  name: string;
  content: string;
  is_active?: boolean;
}

/**
 * Segment prompt configuration by business vertical.
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
 * Payload used to create or update a segment prompt.
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
 * Plan prompt configuration for tenant plan behavior.
 */
export interface PlanPrompt {
  id: string;
  plan_id: string;
  content: string;
  mandatory_rules:
    | {
        rule: string;
        value?: number | string | null;
        required?: boolean;
      }[]
    | null;
  token_limit_monthly: number | null;
  allow_overage: boolean;
  overage_price_per_1k: number | null;
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
 * Payload used to update plan prompt configuration.
 */
export interface PlanPromptPayload {
  content: string;
  mandatory_rules?: { rule: string; value?: number | string | null; required?: boolean }[];
  token_limit_monthly?: number | null;
  allow_overage?: boolean;
  overage_price_per_1k?: number | null;
  is_active?: boolean;
}

/**
 * Tenant prompt under quarantine for manual review.
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
 * Payload used to reject a quarantined prompt.
 */
export interface RejectQuarantinePayload {
  reason: string;
}
