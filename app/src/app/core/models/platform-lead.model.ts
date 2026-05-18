export interface PlatformLead {
  id: string;
  name: string;
  email: string;
  phone: string;
  company?: string | null;
  lgpd_consent: boolean;
  created_at?: string;
  updated_at?: string;
}

export interface PlatformLeadListResponse {
  data: PlatformLead[];
  meta: {
    current_page: number;
    total: number;
    per_page: number;
    last_page: number;
  };
}

export interface PlatformLeadFilters {
  search?: string;
  page?: number;
  per_page?: number;
  sort_by?: 'name' | 'email' | 'created_at';
  sort_dir?: 'asc' | 'desc';
}

export interface PlatformLeadConvertPayload {
  name: string;
  email: string;
  phone: string;
  document?: string | null;
  plan_id?: string | null;
}
