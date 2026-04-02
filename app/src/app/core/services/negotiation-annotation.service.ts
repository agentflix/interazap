import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import type { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { environment } from '@env/environment';

export interface NegotiationAnnotationUser {
  id: string | number;
  name: string;
  avatar?: string | null;
}

export interface NegotiationAnnotation {
  id: string | number;
  negotiation_id: string | number;
  user_id: string | number;
  content: string;
  type: 'manual' | 'system' | 'status' | 'call' | 'email' | 'meeting' | string;
  is_pinned: boolean;
  user?: NegotiationAnnotationUser | null;
  created_at?: string | null;
  updated_at?: string | null;
}

export interface NegotiationAnnotationPayload {
  content: string;
  type?: string;
  is_pinned?: boolean;
}

/**
 * Service for managing negotiation annotations (notes).
 *
 * @remarks
 * Handles CRUD operations for negotiation notes, including pin/unpin functionality.
 */
@Injectable({ providedIn: 'root' })
export class NegotiationAnnotationService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/crm/negotiations`;

  /**
   * Lists all annotations for a negotiation.
   *
   * @param negotiationId - The negotiation identifier
   * @returns Observable with annotations array
   */
  list(
    negotiationId: string | number,
  ): Observable<{ data: { annotations: NegotiationAnnotation[] } }> {
    return this.http
      .get<{
        data: {
          id: string | number;
          content: string;
          author?: { id?: string | number; name?: string } | null;
          created_at?: string | null;
          updated_at?: string | null;
        }[];
      }>(`${this.baseUrl}/${negotiationId}/notes`)
      .pipe(
        map((response) => ({
          data: {
            annotations: (response.data ?? []).map((note) => ({
              id: note.id,
              negotiation_id: negotiationId,
              user_id: note.author?.id ?? '',
              content: note.content,
              type: 'manual',
              is_pinned: false,
              user: {
                id: note.author?.id ?? '',
                name: note.author?.name ?? 'Usuário',
              },
              created_at: note.created_at,
              updated_at: note.updated_at,
            })),
          },
        })),
      );
  }

  /**
   * Creates a new annotation on a negotiation.
   *
   * @param negotiationId - The negotiation identifier
   * @param payload - Annotation content and optional properties
   * @returns Observable with created annotation
   */
  create(
    negotiationId: string | number,
    payload: NegotiationAnnotationPayload,
  ): Observable<{ data: NegotiationAnnotation }> {
    return this.http
      .post<{
        data: {
          id: string | number;
          content: string;
          author?: { id?: string | number; name?: string } | null;
          created_at?: string | null;
          updated_at?: string | null;
        };
      }>(`${this.baseUrl}/${negotiationId}/notes`, { content: payload.content })
      .pipe(
        map((response) => ({
          data: {
            id: response.data.id,
            negotiation_id: negotiationId,
            user_id: response.data.author?.id ?? '',
            content: response.data.content,
            type: 'manual',
            is_pinned: false,
            user: {
              id: response.data.author?.id ?? '',
              name: response.data.author?.name ?? 'Usuário',
            },
            created_at: response.data.created_at,
            updated_at: response.data.updated_at,
          },
        })),
      );
  }

  /**
   * Updates an existing annotation.
   *
   * @param negotiationId - The negotiation identifier
   * @param annotationId - The annotation identifier
   * @param payload - Updated annotation data
   * @returns Observable with updated annotation
   */
  update(
    negotiationId: string | number,
    annotationId: string | number,
    payload: NegotiationAnnotationPayload,
  ): Observable<{ data: NegotiationAnnotation }> {
    return this.http.put<{ data: NegotiationAnnotation }>(
      `${this.baseUrl}/${negotiationId}/annotations/${annotationId}`,
      payload,
    );
  }

  /**
   * Deletes an annotation.
   *
   * @param negotiationId - The negotiation identifier
   * @param annotationId - The annotation identifier
   * @returns Observable completing on deletion
   */
  delete(negotiationId: string | number, annotationId: string | number): Observable<void> {
    return this.http.delete<void>(`${this.baseUrl}/${negotiationId}/annotations/${annotationId}`);
  }

  /**
   * Pins an annotation to the top of the list.
   *
   * @param negotiationId - The negotiation identifier
   * @param annotationId - The annotation identifier
   * @returns Observable with updated annotation
   */
  pin(
    negotiationId: string | number,
    annotationId: string | number,
  ): Observable<{ data: NegotiationAnnotation }> {
    return this.http.post<{ data: NegotiationAnnotation }>(
      `${this.baseUrl}/${negotiationId}/annotations/${annotationId}/pin`,
      {},
    );
  }

  /**
   * Unpins a previously pinned annotation.
   *
   * @param negotiationId - The negotiation identifier
   * @param annotationId - The annotation identifier
   * @returns Observable with updated annotation
   */
  unpin(
    negotiationId: string | number,
    annotationId: string | number,
  ): Observable<{ data: NegotiationAnnotation }> {
    return this.http.delete<{ data: NegotiationAnnotation }>(
      `${this.baseUrl}/${negotiationId}/annotations/${annotationId}/pin`,
    );
  }
}
