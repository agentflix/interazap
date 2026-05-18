export type AutoReplyMatchType = 'exact' | 'contains' | 'starts_with' | 'ends_with' | 'regex';

export type AutoReplyActionType =
  | 'send_message'
  | 'transfer_department'
  | 'transfer_agent'
  | 'close_ticket'
  | 'add_tag'
  | 'create_task';

export interface AutoReplyAction extends Record<string, unknown> {
  type: AutoReplyActionType;
  message?: string;
  message_type?: string;
  department_id?: string;
}

export interface AutoReplyRule {
  id: string;
  company_id: string;
  instance_id?: string | null;
  department_id?: string | null;
  name: string;
  description?: string | null;
  is_welcome?: boolean;
  match_type: AutoReplyMatchType;
  patterns: string[];
  actions: AutoReplyAction[];
  cooldown_seconds: number;
  priority: number;
  is_active: boolean;
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

export interface AutoReplyRuleResponse {
  success: boolean;
  data: AutoReplyRule;
}

export interface AutoReplyKeywordValidationResponse {
  success: boolean;
  data: {
    available: boolean;
  };
}
