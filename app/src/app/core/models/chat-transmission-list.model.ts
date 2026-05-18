export interface ChatTransmissionList {
  id: string;
  tenant_id: string;
  name: string;
  message?: string | null;
  filter_criteria?: {
    tags?: string[];
    status?: string;
    company_id?: string;
  } | null;
  instance_id?: string | null;
  status: 'draft' | 'scheduled' | 'running' | 'completed' | 'failed' | 'cancelled';
  scheduled_at?: string | null;
  sent_at?: string | null;
  created_at: string;
  updated_at: string;
  metadata?: {
    deliveries?: number;
  } | null;
}

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

export interface ChatTransmissionListResponse {
  success: boolean;
  data: ChatTransmissionList;
}

export interface ChatTransmissionListPreview {
  original: string;
  preview: string;
  vars_detected: string[];
  sample_contact: { name: string; phone: string } | null;
  warning?: string;
}
