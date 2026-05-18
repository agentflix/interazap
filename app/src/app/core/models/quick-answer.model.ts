export interface QuickAnswer {
  id: string;
  name: string;
  content: string;
  shortcut?: string;
  is_active: boolean;
  company_id?: string;
  created_at?: string;
  updated_at?: string;
}

export interface QuickAnswerFilters {
  search?: string;
  is_active?: boolean;
  page?: number;
  per_page?: number;
  category?: string;
  sort_by?: string;
  sort_dir?: 'asc' | 'desc';
}
