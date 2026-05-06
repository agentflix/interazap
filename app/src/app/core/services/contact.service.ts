import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';
import {
  type Contact,
  type ContactFilters,
  type ContactImportUploadResponse,
  type ContactImportResponse,
} from '../models/contact.model';
import { type PaginatedResponse } from '../models/pagination.model';

/**
 * Serviço para gestão de contatos do CRM.
 *
 * Centraliza as operações de consulta, criação, atualização e exportação de contatos,
 * integrando os dados com o histórico de atendimentos e empresas.
 *
 * @class ContactService
 * @description Service de domínio para manutenção da base de clientes/leads.
 */
@Injectable({ providedIn: 'root' })
export class ContactService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/crm/contacts`;

  /**
   * Lista os contatos da empresa com suporte a filtros e ordenação.
   * @param filters Critérios de busca e paginação.
   * @returns {Observable<PaginatedResponse<Contact>>} Stream finito com a lista de contatos.
   */
  list(filters: ContactFilters = {}): Observable<PaginatedResponse<Contact>> {
    const params = this.buildListParams(filters);

    return this.http.get<PaginatedResponse<Contact>>(this.baseUrl, { params });
  }

  /**
   * Recupera o perfil detalhado de um contato específico.
   * @param id Identificador do contato.
   * @returns {Observable<{ data: { contact: Contact } }>} Stream finito com os dados do contato.
   */
  find(id: string | number): Observable<{ data: { contact: Contact } }> {
    return this.http.get<{ data: { contact: Contact } }>(`${this.baseUrl}/${id}`);
  }

  /**
   * Cadastra um novo contato no CRM.
   * @param data Dados do novo contato.
   * @returns {Observable<{ data: { contact: Contact } }>} Stream finito com o contato criado.
   */
  create(data: Partial<Contact>): Observable<{ data: { contact: Contact } }> {
    return this.http.post<{ data: { contact: Contact } }>(this.baseUrl, data);
  }

  /**
   * Atualiza as informações de um contato existente.
   * @param id Identificador do contato.
   * @param data Novos atributos para o contato.
   * @returns {Observable<{ data: { contact: Contact } }>} Stream finito com o contato atualizado.
   */
  update(id: string | number, data: Partial<Contact>): Observable<{ data: { contact: Contact } }> {
    return this.http.put<{ data: { contact: Contact } }>(`${this.baseUrl}/${id}`, data);
  }

  /**
   * Atualiza parcialmente um contato existente.
   * @param id Identificador do contato.
   * @param data Atributos a atualizar.
   * @returns {Observable<{ data: { contact: Contact } }>} Stream finito com o contato atualizado.
   */
  patch(
    id: string | number,
    data: Partial<Contact> & { ticket_id?: string | number },
  ): Observable<{ data: { contact: Contact } }> {
    return this.http.patch<{ data: { contact: Contact } }>(`${this.baseUrl}/${id}`, data);
  }

  /**
   * Remove um contato da base de dados.
   * @param id Identificador do contato.
   * @returns {Observable<void>} Stream finito.
   */
  delete(id: string | number): Observable<null> {
    return this.http.delete<null>(`${this.baseUrl}/${id}`);
  }

  /**
   * Alterna o estado de ativação de um contato.
   * @param id Identificador do contato.
   * @returns {Observable<{ data: { contact: Contact } }>} Stream finito com o novo estado.
   */
  toggleActive(id: string | number): Observable<{ data: { contact: Contact } }> {
    return this.http.patch<{ data: { contact: Contact } }>(
      `${this.baseUrl}/${id}/toggle-active`,
      {},
    );
  }

  /**
   * Gera um arquivo de exportação (CSV/Excel) com base nos filtros informados.
   * @param filters Critérios para seleção dos contatos a exportar.
   * @returns {Observable<Blob>} Stream finito com o arquivo binário.
   */
  export(filters: ContactFilters = {}): Observable<Blob> {
    const params = this.buildExportParams(filters);

    return this.http.get(`${this.baseUrl}/export`, {
      params,
      responseType: 'blob',
    });
  }

  private buildListParams(filters: ContactFilters): HttpParams {
    let params = new HttpParams();
    params = this.appendTrimmedString(params, 'search', filters.search);
    params = this.appendBoolean(params, 'is_active', filters.is_active);
    params = this.appendTrimmedString(params, 'tag', filters.tag);
    params = this.appendTrimmedString(params, 'crm_company_id', filters.crm_company_id);
    params = this.appendScalar(params, 'sort_by', filters.sort_by);
    params = this.appendScalar(params, 'sort_dir', filters.sort_dir);
    params = this.appendScalar(params, 'per_page', filters.per_page);
    params = this.appendScalar(params, 'page', filters.page);
    return params;
  }

  private buildExportParams(filters: ContactFilters): HttpParams {
    let params = new HttpParams();
    params = this.appendTrimmedString(params, 'search', filters.search);
    params = this.appendBoolean(params, 'is_active', filters.is_active);
    params = this.appendTrimmedString(params, 'tag', filters.tag);
    params = this.appendScalar(params, 'sort_by', filters.sort_by);
    params = this.appendScalar(params, 'sort_dir', filters.sort_dir);
    return params;
  }

  private appendTrimmedString(params: HttpParams, key: string, value?: string): HttpParams {
    if (value === undefined) return params;
    const trimmed = value.trim();
    if (trimmed.length === 0) return params;
    return params.set(key, trimmed);
  }

  private appendBoolean(params: HttpParams, key: string, value?: boolean): HttpParams {
    if (value === undefined) return params;
    return params.set(key, String(value));
  }

  private appendScalar(
    params: HttpParams,
    key: string,
    value?: string | number | null,
  ): HttpParams {
    if (value === undefined || value === null) return params;
    return params.set(key, String(value));
  }

  /**
   * Upload CSV for import mapping.
   */
  uploadImport(file: File): Observable<ContactImportUploadResponse> {
    const formData = new FormData();
    formData.append('file', file);
    return this.http.post<ContactImportUploadResponse>(`${this.baseUrl}/import/upload`, formData);
  }

  /**
   * Start contact import with mapping.
   */
  importContacts(payload: {
    import_id: string;
    mapping: {
      name: string;
      number: string;
      email?: string;
      company?: string;
    };
    delimiter?: ',' | ';';
    has_header?: boolean;
    instance_id?: string;
  }): Observable<ContactImportResponse> {
    return this.http.post<ContactImportResponse>(`${this.baseUrl}/import`, payload);
  }
}
