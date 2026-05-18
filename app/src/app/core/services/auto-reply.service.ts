import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { type Observable, map } from 'rxjs';
import { environment } from '@env/environment';
import type { AutoReplyKeywordValidationResponse, AutoReplyMatchType, AutoReplyRule, AutoReplyRuleListResponse, AutoReplyRulePayload, AutoReplyRuleResponse } from '@core/models/auto-reply.model';
export type { AutoReplyAction, AutoReplyActionType, AutoReplyKeywordValidationResponse, AutoReplyMatchType, AutoReplyRule, AutoReplyRuleListResponse, AutoReplyRulePayload, AutoReplyRuleResponse } from '@core/models/auto-reply.model';



/**
 * Service for Auto Reply rule management.
 *
 * Responsible for configuring triggers based on keywords and automated actions,
 * such as sending messages or transferring to specific departments.
 *
 * @class AutoReplyService
 * @description Service for controlling input automation flows.
 */
@Injectable({ providedIn: 'root' })
export class AutoReplyService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = `${environment.apiUrl}/chat/auto-reply/rules`;

  /**
   * Gets the list of configured auto reply rules with pagination support.
   * @param params Pagination parameters.
   * @returns {Observable<AutoReplyRuleListResponse>} Stream with encapsulated data.
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

  /**
   * Retrieves details of a specific rule.
   * @param id Rule identifier.
   * @returns {Observable<AutoReplyRuleResponse>} Stream with the rule.
   */
  show(id: string): Observable<AutoReplyRuleResponse> {
    return this.http
      .get<{ data: AutoReplyRule }>(`${this.apiUrl}/${id}`)
      .pipe(map((resp) => ({ success: true, data: resp.data })));
  }

  /**
   * Creates a new auto reply rule.
   * @param payload Rule data.
   * @returns {Observable<AutoReplyRuleResponse>} Stream with the created rule.
   */
  create(payload: AutoReplyRulePayload): Observable<AutoReplyRuleResponse> {
    return this.http
      .post<{ data: AutoReplyRule }>(this.apiUrl, payload)
      .pipe(map((resp) => ({ success: true, data: resp.data })));
  }

  /**
   * Updates an existing auto reply rule.
   * @param id Rule identifier.
   * @param payload Attributes to be changed.
   * @returns {Observable<AutoReplyRuleResponse>} Stream with the updated rule.
   */
  update(id: string, payload: Partial<AutoReplyRulePayload>): Observable<AutoReplyRuleResponse> {
    return this.http
      .put<{ data: AutoReplyRule }>(`${this.apiUrl}/${id}`, payload)
      .pipe(map((resp) => ({ success: true, data: resp.data })));
  }

  /**
   * Removes a rule from the system.
   * @param id Rule identifier.
   * @returns {Observable<void>} Stream.
   */
  delete(id: string): Observable<null> {
    return this.http.delete<null>(`${this.apiUrl}/${id}`);
  }

  /**
   * Toggles the status (active/inactive) of a rule.
   * @param id Rule identifier.
   * @returns {Observable<AutoReplyRuleResponse>} Stream with the new status.
   */
  toggle(id: string): Observable<AutoReplyRuleResponse> {
    return this.http.patch<AutoReplyRuleResponse>(`${this.apiUrl}/${id}/toggle`, {});
  }

  /**
   * Validates if a keyword is available for use in the current context.
   * @param params Validation criteria (keyword, instance, etc).
   * @returns {Observable<AutoReplyKeywordValidationResponse>} Stream with availability result.
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
