import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { type Observable, map } from 'rxjs';
import { environment } from '@env/environment';

/** Primitive metadata shape returned by campaign API. */
interface CampaignMeta {
  current_page?: number;
  last_page?: number;
  per_page?: number;
  total?: number;
}

/** Chat campaign entity used by campaigns screens. */
export interface ChatCampaign {
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

/** Payload used to create/update a campaign. */
export interface ChatCampaignPayload {
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

/** API shape returned by list endpoint after normalization. */
export interface ChatCampaignListResponse {
  success: boolean;
  data: {
    data: ChatCampaign[];
    current_page?: number;
    last_page?: number;
    per_page?: number;
    total?: number;
  };
}

interface ChatCampaignListEnvelope {
  data?: ChatCampaign[];
  meta?: CampaignMeta;
}

interface ChatCampaignListNestedEnvelope {
  data?: {
    data?: ChatCampaign[];
    meta?: CampaignMeta;
  };
  meta?: CampaignMeta;
}

/** API shape returned by single item endpoints. */
export interface ChatCampaignResponse {
  success: boolean;
  data: ChatCampaign;
}

/** Preview API response payload. */
export interface ChatCampaignPreview {
  original: string;
  preview: string;
  vars_detected: string[];
  sample_contact: { name: string; phone: string } | null;
  warning?: string;
}

/** Service responsible for Chat Campaign CRUD and helper endpoints. */
@Injectable({ providedIn: 'root' })
export class ChatCampaignService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = `${environment.apiUrl}/chat/campaigns`;

  /** Lists campaigns with pagination and optional search. */
  list(
    params: { per_page?: number; page?: number; search?: string } = {},
  ): Observable<ChatCampaignListResponse> {
    let httpParams = new HttpParams();

    if (params.per_page) {
      httpParams = httpParams.set('per_page', String(params.per_page));
    }
    if (params.page) {
      httpParams = httpParams.set('page', String(params.page));
    }
    if (params.search) {
      httpParams = httpParams.set('search', params.search);
    }

    return this.http
      .get<
        ChatCampaignListEnvelope | ChatCampaignListNestedEnvelope
      >(this.apiUrl, { params: httpParams })
      .pipe(
        map((response) => {
          const data = this.extractData(response);
          const meta = this.extractMeta(response);

          return {
            success: true,
            data: {
              data,
              current_page: meta?.current_page,
              last_page: meta?.last_page,
              per_page: meta?.per_page,
              total: meta?.total,
            },
          };
        }),
      );
  }

  /** Loads a campaign by id. */
  show(id: string): Observable<ChatCampaignResponse> {
    return this.http
      .get<{ data: ChatCampaign }>(`${this.apiUrl}/${id}`)
      .pipe(map((response) => ({ success: true, data: response.data })));
  }

  /** Creates a new campaign. */
  create(payload: ChatCampaignPayload): Observable<ChatCampaignResponse> {
    return this.http
      .post<{ data: ChatCampaign }>(this.apiUrl, payload)
      .pipe(map((response) => ({ success: true, data: response.data })));
  }

  /** Updates an existing campaign. */
  update(id: string, payload: Partial<ChatCampaignPayload>): Observable<ChatCampaignResponse> {
    return this.http
      .put<{ data: ChatCampaign }>(`${this.apiUrl}/${id}`, payload)
      .pipe(map((response) => ({ success: true, data: response.data })));
  }

  /** Deletes a campaign. */
  delete(id: string): Observable<null> {
    return this.http.delete<null>(`${this.apiUrl}/${id}`);
  }

  /** Triggers campaign sending. */
  send(id: string): Observable<null> {
    return this.http.post<null>(`${this.apiUrl}/${id}/send`, {});
  }

  /** Generates message preview based on contact sample interpolation. */
  preview(message: string): Observable<ChatCampaignPreview> {
    return this.http
      .post<{ data: ChatCampaignPreview }>(`${this.apiUrl}/preview`, { message })
      .pipe(map((response) => response.data));
  }

  /** Calculates target audience estimate for current filter criteria. */
  audience(criteria: {
    tags?: string[];
    status?: string;
    company_id?: string | null;
  }): Observable<{ count: number }> {
    return this.http
      .post<{ success: boolean; data: { count: number } }>(`${this.apiUrl}/audience`, { criteria })
      .pipe(map((response) => response.data));
  }

  private extractData(
    response: ChatCampaignListEnvelope | ChatCampaignListNestedEnvelope,
  ): ChatCampaign[] {
    if (Array.isArray(response.data)) {
      return response.data;
    }

    if (response.data && Array.isArray(response.data.data)) {
      return response.data.data;
    }

    return [];
  }

  private extractMeta(
    response: ChatCampaignListEnvelope | ChatCampaignListNestedEnvelope,
  ): CampaignMeta | undefined {
    if (response.data && !Array.isArray(response.data)) {
      return response.data.meta ?? response.meta;
    }

    return response.meta;
  }
}
