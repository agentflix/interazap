import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import type { Observable } from 'rxjs';
import { environment } from '@env/environment';
import {
  type PlanChangePreview,
  type PlanChangeResult,
  type SubscriptionPlan,
  type SubscriptionSummary,
} from '@shared/models/subscription.model';

interface ApiResponse<T> {
  data: T;
}

/**
 * Gerencia assinatura do tenant na tela "Meu Plano".
 *
 * Cobre consulta da assinatura ativa, listagem de planos disponíveis,
 * preview de troca de plano e execução da mudança com confirmação por senha.
 */
@Injectable({ providedIn: 'root' })
export class BillingSubscriptionService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/billing`;

  /**
   * Retorna resumo da assinatura atual do tenant.
   */
  getSubscription(): Observable<ApiResponse<SubscriptionSummary>> {
    return this.http.get<ApiResponse<SubscriptionSummary>>(`${this.baseUrl}/subscription`);
  }

  /**
   * Lista planos ativos disponíveis para troca.
   */
  getPlans(): Observable<ApiResponse<SubscriptionPlan[]>> {
    return this.http.get<ApiResponse<SubscriptionPlan[]>>(`${this.baseUrl}/plans`);
  }

  /**
   * Retorna preview do impacto da troca de plano.
   */
  previewPlanChange(planId: string): Observable<ApiResponse<PlanChangePreview>> {
    const params = new HttpParams().set('plan_id', planId);

    return this.http.get<ApiResponse<PlanChangePreview>>(`${this.baseUrl}/plan-change/preview`, {
      params,
    });
  }

  /**
   * Executa troca de plano com confirmação por senha atual.
   */
  changePlan(planId: string, currentPassword: string): Observable<ApiResponse<PlanChangeResult>> {
    return this.http.post<ApiResponse<PlanChangeResult>>(`${this.baseUrl}/plan-change`, {
      plan_id: planId,
      current_password: currentPassword,
    });
  }
}
