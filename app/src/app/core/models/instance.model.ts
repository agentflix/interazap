export type InstanceStatus =
  | 'connected'
  | 'connecting'
  | 'disconnected'
  | 'failed'
  | 'qr'
  | 'pairing';

export interface Instance {
  id: string | number;
  name?: string | null;
  phone?: string | null;
  provider?: string | null;
  status?: InstanceStatus | null;
  connection_status?: InstanceStatus | string | null;
  is_active?: boolean;
  is_connected?: boolean;
  company_id?: string | number;
  integration_id?: string | number;
  settings?: Record<string, unknown> | null;
  token?: string | null;
  created_at?: string;
  updated_at?: string;
}

export interface InstanceFilters {
  search?: string;
  integration_id?: string | number;
  status?: InstanceStatus;
  is_active?: boolean;
  per_page?: number;
  page?: number;
}

export interface InstanceListResponse {
  data: Instance[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}
