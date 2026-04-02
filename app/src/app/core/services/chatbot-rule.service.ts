import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { type Observable, map } from 'rxjs';
import { environment } from '@env/environment';

export type ChatbotMatchType = 'exact' | 'contains' | 'starts_with' | 'ends_with' | 'regex';

export type ChatbotActionType =
  | 'send_message'
  | 'transfer_department'
  | 'transfer_agent'
  | 'close_ticket'
  | 'add_tag'
  | 'create_task';

export interface ChatbotAction extends Record<string, unknown> {
  type: ChatbotActionType;
  message?: string;
  message_type?: string;
  department_id?: string;
}

export interface ChatbotRule {
  id: string;
  company_id: string;
  instance_id?: string | null;
  department_id?: string | null;
  name: string;
  description?: string | null;
  is_welcome?: boolean;
  match_type: ChatbotMatchType;
  patterns: string[];
  actions: ChatbotAction[];
  cooldown_seconds: number;
  priority: number;
  is_active: boolean;
  respect_business_hours: boolean;
  department?: {
    id: string;
    name: string;
  } | null;
  instance?: {
    id: string;
    name?: string | null;
    phone?: string | null;
  } | null;
  created_at: string;
  updated_at: string;
}

export interface ChatbotRulePayload {
  name: string;
  description?: string | null;
  instance_id?: string | null;
  department_id?: string | null;
  is_welcome?: boolean;
  match_type: ChatbotMatchType;
  patterns: string[];
  actions: ChatbotAction[];
  cooldown_seconds?: number;
  priority?: number;
  is_active?: boolean;
  respect_business_hours?: boolean;
}

export interface ChatbotRuleListResponse {
  success: boolean;
  data: {
    data: ChatbotRule[];
    current_page?: number;
    last_page?: number;
    per_page?: number;
    total?: number;
  };
}

export interface ChatbotRuleResponse {
  success: boolean;
  data: ChatbotRule;
}

export interface ChatbotKeywordValidationResponse {
  success: boolean;
  data: {
    available: boolean;
  };
}

/**
 * Serviço para gestão de regras do Chatbot.
 *
 * Responsável por configurar gatilhos baseados em palavras-chave e ações automatizadas,
 * como envio de mensagens ou transferência de departamento.
 *
 * @class ChatbotRuleService
 * @description Service para controle de fluxos de automação de entrada.
 */
@Injectable({ providedIn: 'root' })
export class ChatbotRuleService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = `${environment.apiUrl}/chat/chatbot/rules`;

  /**
   * Obtém a lista de regras de chatbot configuradas com suporte a paginação.
   * @param params Parâmetros de paginação.
   * @returns {Observable<ChatbotRuleListResponse>} Stream finito com dados encapsulados.
   */
  list(params: { per_page?: number; page?: number } = {}): Observable<ChatbotRuleListResponse> {
    let httpParams = new HttpParams();
    if (params.per_page) httpParams = httpParams.set('per_page', String(params.per_page));
    if (params.page) httpParams = httpParams.set('page', String(params.page));

    return this.http
      .get<{
        data: ChatbotRule[];
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

  /**
   * Recupera detalhes de uma regra específica.
   * @param id Identificador da regra.
   * @returns {Observable<ChatbotRuleResponse>} Stream finito com a regra.
   */
  show(id: string): Observable<ChatbotRuleResponse> {
    return this.http
      .get<{ data: ChatbotRule }>(`${this.apiUrl}/${id}`)
      .pipe(map((resp) => ({ success: true, data: resp.data })));
  }

  /**
   * Cria uma nova regra de chatbot.
   * @param payload Dados da regra.
   * @returns {Observable<ChatbotRuleResponse>} Stream finito com a regra criada.
   */
  create(payload: ChatbotRulePayload): Observable<ChatbotRuleResponse> {
    return this.http
      .post<{ data: ChatbotRule }>(this.apiUrl, payload)
      .pipe(map((resp) => ({ success: true, data: resp.data })));
  }

  /**
   * Atualiza uma regra de chatbot existente.
   * @param id Identificador da regra.
   * @param payload Atributos a serem alterados.
   * @returns {Observable<ChatbotRuleResponse>} Stream finito com a regra atualizada.
   */
  update(id: string, payload: Partial<ChatbotRulePayload>): Observable<ChatbotRuleResponse> {
    return this.http
      .put<{ data: ChatbotRule }>(`${this.apiUrl}/${id}`, payload)
      .pipe(map((resp) => ({ success: true, data: resp.data })));
  }

  /**
   * Remove uma regra do sistema.
   * @param id Identificador da regra.
   * @returns {Observable<void>} Stream finito.
   */
  delete(id: string): Observable<null> {
    return this.http.delete<null>(`${this.apiUrl}/${id}`);
  }

  /**
   * Alterna o status (ativo/inativo) de uma regra.
   * @param id Identificador da regra.
   * @returns {Observable<ChatbotRuleResponse>} Stream finito com o novo status.
   */
  toggle(id: string): Observable<ChatbotRuleResponse> {
    return this.http.patch<ChatbotRuleResponse>(`${this.apiUrl}/${id}/toggle`, {});
  }

  /**
   * Valida se uma palavra-chave está disponível para uso no contexto atual.
   * @param params Critérios de validação (palavra, instância, etc).
   * @returns {Observable<ChatbotKeywordValidationResponse>} Stream finito com resultado da disponibilidade.
   */
  validateKeyword(params: {
    keyword: string;
    match_type?: ChatbotMatchType;
    instance_id?: string | null;
    department_id?: string | null;
    rule_id?: string | null;
  }): Observable<ChatbotKeywordValidationResponse> {
    let httpParams = new HttpParams();
    httpParams = httpParams.set('keyword', params.keyword);
    if (params.match_type) httpParams = httpParams.set('match_type', params.match_type);
    if (params.instance_id) httpParams = httpParams.set('instance_id', params.instance_id);
    if (params.department_id) httpParams = httpParams.set('department_id', params.department_id);
    if (params.rule_id) httpParams = httpParams.set('rule_id', params.rule_id);

    return this.http.get<ChatbotKeywordValidationResponse>(`${this.apiUrl}/validate-keyword`, {
      params: httpParams,
    });
  }
}
