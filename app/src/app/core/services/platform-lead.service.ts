import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { environment } from '@env/environment';
import { type Observable } from 'rxjs';
import type { PlatformLead, PlatformLeadConvertPayload, PlatformLeadFilters, PlatformLeadListResponse } from '@core/models/platform-lead.model';
export type { PlatformLead, PlatformLeadConvertPayload, PlatformLeadFilters, PlatformLeadListResponse } from '@core/models/platform-lead.model';



interface ApiDataResponse<T> {
  success: boolean;
  message: string;
  data: T;
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
