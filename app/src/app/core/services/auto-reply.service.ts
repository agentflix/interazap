import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { type Observable, map } from 'rxjs';
import { environment } from '@env/environment';
import type { AutoReplyKeywordValidationResponse, AutoReplyMatchType, AutoReplyRule, AutoReplyRuleListResponse, AutoReplyRulePayload, AutoReplyRuleResponse } from '@core/models/auto-reply.model';
export type { AutoReplyAction, AutoReplyActionType, AutoReplyKeywordValidationResponse, AutoReplyMatchType, AutoReplyRule, AutoReplyRuleListResponse, AutoReplyRulePayload, AutoReplyRuleResponse } from '@core/models/auto-reply.model';



/**
 * Gerencia regras de resposta automática (auto reply).
 *
 * Responsável por configurar gatilhos baseados em palavras-chave e ações
 * automatizadas, como envio de mensagens ou transferência para departamentos
 * específicos.
 */
@Injectable({ providedIn: 'root' })
export class AutoReplyService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = `${environment.apiUrl}/chat/auto-reply/rules`;

  /**
   * Retorna lista paginada de regras de auto reply configuradas.
   * @param params Parâmetros de paginação: per_page, page
   * @returns Observable com lista encapsulada de regras
   */
  list(params: { per_page?: number; page?: number } = {}): Observable<AutoReplyRuleListResponse> {
    let httpParams = new HttpParams();
    if (params.per_page) httpParams = httpParams.set('per_page', String(params.per_page));
    if (params.page) httpParams = httpParams.set('page', String(params.page));

    return this.http
      .get<{
        data: AutoReplyRule[];
        meta?: { current_page?: number; last_page?: number; per_page?: number; total?: number };
      }>(this.apiUrl, { params: httpParams })
      .pipe(
        map((resp) => {
          const data = Array.isArray(resp.data) ? resp.data : [];
          const meta = resp.meta;
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

  /** Retorna os detalhes de uma regra específica pelo ID. */
  show(id: string): Observable<AutoReplyRuleResponse> {
    return this.http
      .get<{ data: AutoReplyRule }>(`${this.apiUrl}/${id}`)
      .pipe(map((resp) => ({ success: true, data: resp.data })));
  }

  /** Cria uma nova regra de auto reply. */
  create(payload: AutoReplyRulePayload): Observable<AutoReplyRuleResponse> {
    return this.http
      .post<{ data: AutoReplyRule }>(this.apiUrl, payload)
      .pipe(map((resp) => ({ success: true, data: resp.data })));
  }

  /** Atualiza atributos de uma regra de auto reply existente. */
  update(id: string, payload: Partial<AutoReplyRulePayload>): Observable<AutoReplyRuleResponse> {
    return this.http
      .put<{ data: AutoReplyRule }>(`${this.apiUrl}/${id}`, payload)
      .pipe(map((resp) => ({ success: true, data: resp.data })));
  }

  /** Remove uma regra de auto reply do sistema. */
  delete(id: string): Observable<null> {
    return this.http.delete<null>(`${this.apiUrl}/${id}`);
  }

  /** Alterna o status ativo/inativo de uma regra de auto reply. */
  toggle(id: string): Observable<AutoReplyRuleResponse> {
    return this.http.patch<AutoReplyRuleResponse>(`${this.apiUrl}/${id}/toggle`, {});
  }

  /**
   * Valida se uma palavra-chave está disponível para uso no contexto informado.
   * @param params Critérios de validação: keyword, match_type, instance_id, department_id, rule_id
   * @returns Observable com resultado de disponibilidade da palavra-chave
   */
  validateKeyword(params: {
    keyword: string;
    match_type?: AutoReplyMatchType;
    instance_id?: string | null;
    department_id?: string | null;
    rule_id?: string | null;
  }): Observable<AutoReplyKeywordValidationResponse> {
    let httpParams = new HttpParams();
    httpParams = httpParams.set('keyword', params.keyword);
    if (params.match_type) httpParams = httpParams.set('match_type', params.match_type);
    if (params.instance_id) httpParams = httpParams.set('instance_id', params.instance_id);
    if (params.department_id) httpParams = httpParams.set('department_id', params.department_id);
    if (params.rule_id) httpParams = httpParams.set('rule_id', params.rule_id);

    return this.http.get<AutoReplyKeywordValidationResponse>(`${this.apiUrl}/validate-keyword`, {
      params: httpParams,
    });
  }
}
