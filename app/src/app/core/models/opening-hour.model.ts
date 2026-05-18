export interface OpeningHour {
  id: string;
  company_id: string;
  day_of_week: number;
  open_time: string;
  close_time: string;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

export interface OpeningHourResponse {
  success: boolean;
  message?: string;
  data: OpeningHour;
}

export interface OpeningHourListResponse {
  success: boolean;
  data: {
    opening_hours: OpeningHour[];
  };
}

export interface BulkUpdateOpeningHoursRequest {
  opening_hours: {
    day_of_week: number;
    open_time: string;
    close_time: string;
    is_active: boolean;
  }[];
}
