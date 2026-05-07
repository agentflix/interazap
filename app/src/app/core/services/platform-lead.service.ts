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
  source: string;
  status: string;
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
  status?: string;
  source?: string;
  page?: number;
  per_page?: number;
  sort_by?: 'name' | 'email' | 'status' | 'source' | 'created_at';
  sort_dir?: 'asc' | 'desc';
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
}
