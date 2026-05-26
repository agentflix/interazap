import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';
import type {
  Contact,
  ContactFilters,
  ContactListResponse,
  ContactResponse,
  ContactImportUploadResponse,
  ContactImportResponse,
} from '@core/models/contact.model';

/**
 * Gerencia contatos do CRM com operações de CRUD, importação/exportação e controle de status.
 */
@Injectable({ providedIn: 'root' })
export class ContactService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/crm/contacts`;

  /**
   * Retorna lista paginada de contatos com base nos filtros fornecidos.
   * @param filters - Parâmetros opcionais de filtragem e paginação.
   * @returns Observable de `ContactListResponse`.
   */
  list(filters: ContactFilters = {}): Observable<ContactListResponse> {
    let params = new HttpParams();
    params = this.appendTrimmedString(params, 'search', filters.search);
    params = this.appendBoolean(params, 'is_active', filters.is_active);
    params = this.appendTrimmedString(params, 'tag', filters.tag);
    params = this.appendTrimmedString(params, 'sort_by', filters.sort_by);
    params = this.appendSortDir(params, filters.sort_dir);
    params = this.appendNumber(params, 'per_page', filters.per_page);
    params = this.appendNumber(params, 'page', filters.page);
    return this.http.get<ContactListResponse>(this.baseUrl, { params });
  }

  /**
   * Retorna um contato pelo seu ID.
   * @param id - Identificador do contato.
   * @returns Observable de `ContactResponse`.
   */
  find(id: string): Observable<ContactResponse> {
    return this.http.get<ContactResponse>(`${this.baseUrl}/${id}`);
  }

  /**
   * Cria um novo contato no sistema.
   * @param data - Dados parciais do contato para criação.
   * @returns Observable de `ContactResponse`.
   */
  create(data: Partial<Contact>): Observable<ContactResponse> {
    return this.http.post<ContactResponse>(this.baseUrl, data);
  }

  /**
   * Atualiza um contato existente.
   * @param id - Identificador do contato.
   * @param data - Dados parciais do contato para atualização.
   * @returns Observable de `ContactResponse`.
   */
  update(id: string, data: Partial<Contact>): Observable<ContactResponse> {
    return this.http.put<ContactResponse>(`${this.baseUrl}/${id}`, data);
  }

  /**
   * Exclui um contato do sistema.
   * @param id - Identificador do contato.
   * @returns Observable de `null` em caso de sucesso.
   */
  delete(id: string): Observable<null> {
    return this.http.delete<null>(`${this.baseUrl}/${id}`);
  }

  /**
   * Alterna o status ativo/inativo de um contato.
   * @param id - Identificador do contato.
   * @returns Observable de `ContactResponse` atualizado.
   */
  toggleActive(id: string): Observable<ContactResponse> {
    return this.http.patch<ContactResponse>(`${this.baseUrl}/${id}/toggle-active`, {});
  }

  /**
   * Exporta contatos para um arquivo CSV.
   * @param filters - Filtros opcionais a aplicar na exportação.
   * @returns Observable de `Blob` contendo os dados CSV.
   */
  export(filters: ContactFilters = {}): Observable<Blob> {
    let params = new HttpParams();
    params = this.appendTrimmedString(params, 'search', filters.search);
    params = this.appendBoolean(params, 'is_active', filters.is_active);
    params = this.appendTrimmedString(params, 'tag', filters.tag);
    return this.http.get(`${this.baseUrl}/export`, { params, responseType: 'blob' });
  }

  /**
   * Faz upload de um arquivo CSV para preparação da importação.
   * @param file - Arquivo CSV a enviar.
   * @returns Observable de `ContactImportUploadResponse` com informações de mapeamento.
   */
  uploadImport(file: File): Observable<ContactImportUploadResponse> {
    const formData = new FormData();
    formData.append('file', file);
    return this.http.post<ContactImportUploadResponse>(`${this.baseUrl}/import/upload`, formData);
  }

  /**
   * Finaliza o processo de importação de contatos com mapeamento de campos.
   * @param payload - Detalhes da importação incluindo mapeamento e delimitador.
   * @returns Observable de `ContactImportResponse`.
   */
  importContacts(payload: {
    import_id: string;
    mapping: { name: string; number: string; email?: string; company?: string };
    delimiter?: ',' | ';';
    has_header?: boolean;
  }): Observable<ContactImportResponse> {
    return this.http.post<ContactImportResponse>(`${this.baseUrl}/import`, payload);
  }

  /**
   * Auxiliar: adiciona string sem espaços extras aos HttpParams se não estiver vazia.
   * @private
   */
  private appendTrimmedString(params: HttpParams, key: string, value?: string): HttpParams {
    if (value === undefined) return params;
    const trimmed = value.trim();
    if (trimmed.length === 0) return params;
    return params.set(key, trimmed);
  }

  /**
   * Auxiliar: adiciona valor booleano aos HttpParams.
   * @private
   */
  private appendBoolean(params: HttpParams, key: string, value?: boolean): HttpParams {
    if (value === undefined) return params;
    return params.set(key, String(value));
  }

  /**
   * Auxiliar: adiciona direção de ordenação aos HttpParams.
   * @private
   */
  private appendSortDir(params: HttpParams, value?: 'asc' | 'desc'): HttpParams {
    if (value === undefined) return params;
    return params.set('sort_dir', value);
  }

  /**
   * Auxiliar: adiciona valor numérico aos HttpParams.
   * @private
   */
  private appendNumber(params: HttpParams, key: string, value?: number): HttpParams {
    if (value === undefined) return params;
    return params.set(key, String(value));
  }
}
