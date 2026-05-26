import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import type { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { environment } from '@env/environment';
import type { NegotiationAnnotation, NegotiationAnnotationPayload } from '@core/models/negotiation.model';
export type { NegotiationAnnotation, NegotiationAnnotationPayload, NegotiationAnnotationUser } from '@core/models/negotiation.model';



/**
 * Gerencia anotações (notas) de negociações do CRM.
 *
 * @remarks
 * Fornece operações de CRUD para notas de negociação, incluindo fixar/desafixar.
 */
@Injectable({ providedIn: 'root' })
export class NegotiationAnnotationService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/crm/negotiations`;

  /**
   * Lista todas as anotações de uma negociação.
   *
   * @param negotiationId - Identificador da negociação
   * @returns Observable com o array de anotações
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
   * Cria uma nova anotação em uma negociação.
   *
   * @param negotiationId - Identificador da negociação
   * @param payload - Conteúdo da anotação e propriedades opcionais
   * @returns Observable com a anotação criada
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
   * Atualiza uma anotação existente.
   *
   * @param negotiationId - Identificador da negociação
   * @param annotationId - Identificador da anotação
   * @param payload - Dados atualizados da anotação
   * @returns Observable com a anotação atualizada
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
   * Exclui uma anotação.
   *
   * @param negotiationId - Identificador da negociação
   * @param annotationId - Identificador da anotação
   * @returns Observable que completa após a exclusão
   */
  delete(negotiationId: string | number, annotationId: string | number): Observable<void> {
    return this.http.delete<void>(`${this.baseUrl}/${negotiationId}/annotations/${annotationId}`);
  }

  /**
   * Fixa uma anotação no topo da lista.
   *
   * @param negotiationId - Identificador da negociação
   * @param annotationId - Identificador da anotação
   * @returns Observable com a anotação atualizada
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
   * Remove a fixação de uma anotação previamente fixada.
   *
   * @param negotiationId - Identificador da negociação
   * @param annotationId - Identificador da anotação
   * @returns Observable com a anotação atualizada
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
