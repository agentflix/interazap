import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { environment } from '@env/environment';
import { type Observable } from 'rxjs';

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

interface ApiDataResponse<T> {
  success: boolean;
  message: string;
  data: T;
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

@Injectable({ providedIn: 'root' })
export class PlatformLeadService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = `${environment.apiUrl}/platform/leads`;

  list(filters: PlatformLeadFilters = {}): Observable<PlatformLeadListResponse> {
    let params = new HttpParams();

    Object.entries(filters).forEach(([key, value]) => {
      if (value !== undefined && value !== null && String(value).trim() !== '') {
        params = params.set(key, String(value));
      }
    });

    return this.http.get<PlatformLeadListResponse>(this.apiUrl, { params });
  }

  convert(
    id: string,
    payload: PlatformLeadConvertPayload,
  ): Observable<ApiDataResponse<PlatformLead>> {
    return this.http.post<ApiDataResponse<PlatformLead>>(`${this.apiUrl}/${id}/convert`, payload);
  }

  export(filters: PlatformLeadFilters = {}): Observable<Blob> {
    let params = new HttpParams();

    Object.entries(filters).forEach(([key, value]) => {
      if (value !== undefined && value !== null && String(value).trim() !== '') {
        params = params.set(key, String(value));
      }
    });

    return this.http.get(`${this.apiUrl}/export`, {
      params,
      responseType: 'blob',
    });
  }
}
