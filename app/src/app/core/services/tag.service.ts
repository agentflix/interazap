import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';
import type { Tag, TagResponse, TagListResponse, TagFilters } from '@core/models/tag.model';

@Injectable({
  providedIn: 'root',
})
export class TagService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = `${environment.apiUrl}/crm/tags`;

  /**
   * Lista tags com suporte a filtros e paginacao.
   *
   * @param params - Filtros: search, is_active, per_page, page, sort_by, sort_dir
   * @returns Observable com lista paginada de tags
   */
  list(params: TagFilters): Observable<TagListResponse> {
    let httpParams = new HttpParams();
    Object.keys(params).forEach((key) => {
      const value = (params as Record<string, unknown>)[key];
      if (value !== undefined && value !== null) {
        httpParams = httpParams.set(key, String(value));
      }
    });
    return this.http.get<TagListResponse>(this.apiUrl, { params: httpParams });
  }

  /**
   * Lista todas as tags sem paginacao.
   *
   * @returns Observable com array de tags
   */
  all(): Observable<{ success: boolean; data: { tags: Tag[] } }> {
    return this.http.get<{ success: boolean; data: { tags: Tag[] } }>(`${this.apiUrl}/all`);
  }

  /**
   * Obtem detalhes de uma tag especifica.
   *
   * @param id - Identificador da tag
   * @returns Observable com dados da tag
   */
  show(id: string): Observable<TagResponse> {
    return this.http.get<TagResponse>(`${this.apiUrl}/${id}`);
  }

  /**
   * Cria uma nova tag.
   *
   * @param data - Dados da tag (name, color, category, etc)
   * @returns Observable com a tag criada
   */
  create(data: Partial<Tag>): Observable<TagResponse> {
    return this.http.post<TagResponse>(this.apiUrl, data);
  }

  /**
   * Atualiza uma tag existente.
   *
   * @param id - Identificador da tag
   * @param data - Dados a serem atualizados
   * @returns Observable com a tag atualizada
   */
  update(id: string, data: Partial<Tag>): Observable<TagResponse> {
    return this.http.put<TagResponse>(`${this.apiUrl}/${id}`, data);
  }

  /**
   * Remove uma tag.
   *
   * @param id - Identificador da tag
   * @returns Observable vazio indicando sucesso
   */
  delete(id: string): Observable<null> {
    return this.http.delete<null>(`${this.apiUrl}/${id}`);
  }

  /**
   * Vincula uma tag a um contato.
   *
   * @param contactId - Identificador do contato
   * @param tagId - Identificador da tag
   * @returns Observable com indicador de sucesso
   */
  attachToContact(
    contactId: string,
    tagId: string,
  ): Observable<{ success: boolean; message?: string }> {
    return this.http.post<{ success: boolean; message?: string }>(
      `${environment.apiUrl}/crm/contacts/${contactId}/tags`,
      { tag_id: tagId },
    );
  }

  /**
   * Desvincula uma tag de um contato.
   *
   * @param contactId - Identificador do contato
   * @param tagId - Identificador da tag
   * @returns Observable vazio indicando sucesso
   */
  detachFromContact(contactId: string, tagId: string): Observable<null> {
    return this.http.delete<null>(`${environment.apiUrl}/crm/contacts/${contactId}/tags/${tagId}`);
  }
}
