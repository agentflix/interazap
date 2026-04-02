import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';
import {
  type AgentPerformanceReport,
  type AiUsageReport,
  type AutopilotReport,
  type BillingReport,
  type ChatVolumeReport,
  type ContactCrmReport,
  type CsatNpsReport,
  type LossReasonReport,
  type MediaTranscriptionReport,
  type ProductReport,
  type ReportFilter,
  type ReportResponse,
  type RevenueSalesReport,
  type SalesFunnelReport,
  type SalespersonReport,
  type SlaReport,
  type TeamActivityReport,
} from '../../shared/models/report.model';

/**
 * Serviço para chamadas HTTP aos endpoints de relatórios.
 */
@Injectable({ providedIn: 'root' })
export class ReportsService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/reports`;

  /** Monta HttpParams a partir dos filtros. */
  private buildParams(filters: ReportFilter): HttpParams {
    let params = new HttpParams()
      .set('start_date', filters.start_date)
      .set('end_date', filters.end_date);

    if (filters.granularity) params = params.set('granularity', filters.granularity);
    if (filters.user_id) params = params.set('user_id', filters.user_id);
    if (filters.funnel_id) params = params.set('funnel_id', filters.funnel_id);
    if (filters.step_id) params = params.set('step_id', filters.step_id);
    if (filters.channel) params = params.set('channel', filters.channel);
    if (filters.instance_id) params = params.set('instance_id', filters.instance_id);
    if (filters.reason_loss_id) params = params.set('reason_loss_id', filters.reason_loss_id);
    if (filters.product_id) params = params.set('product_id', filters.product_id);

    return params;
  }

  /** Relatório de funil de vendas. */
  getSalesFunnel(filters: ReportFilter): Observable<ReportResponse<SalesFunnelReport>> {
    return this.http.get<ReportResponse<SalesFunnelReport>>(`${this.baseUrl}/sales-funnel`, {
      params: this.buildParams(filters),
    });
  }

  /** Relatório de receita e vendas. */
  getRevenueSales(filters: ReportFilter): Observable<ReportResponse<RevenueSalesReport>> {
    return this.http.get<ReportResponse<RevenueSalesReport>>(`${this.baseUrl}/revenue-sales`, {
      params: this.buildParams(filters),
    });
  }

  /** Relatório de performance de vendedores. */
  getSalespersonPerformance(filters: ReportFilter): Observable<ReportResponse<SalespersonReport>> {
    return this.http.get<ReportResponse<SalespersonReport>>(
      `${this.baseUrl}/salesperson-performance`,
      { params: this.buildParams(filters) },
    );
  }

  /** Relatório de motivos de perda. */
  getLossReasons(filters: ReportFilter): Observable<ReportResponse<LossReasonReport>> {
    return this.http.get<ReportResponse<LossReasonReport>>(`${this.baseUrl}/loss-reasons`, {
      params: this.buildParams(filters),
    });
  }

  /** Relatório de SLA e tempo de resolução. */
  getSlaResolution(filters: ReportFilter): Observable<ReportResponse<SlaReport>> {
    return this.http.get<ReportResponse<SlaReport>>(`${this.baseUrl}/sla-resolution`, {
      params: this.buildParams(filters),
    });
  }

  /** Relatório de performance de agentes. */
  getAgentPerformance(filters: ReportFilter): Observable<ReportResponse<AgentPerformanceReport>> {
    return this.http.get<ReportResponse<AgentPerformanceReport>>(
      `${this.baseUrl}/agent-performance`,
      { params: this.buildParams(filters) },
    );
  }

  /** Relatório de CSAT / NPS. */
  getCsatNps(filters: ReportFilter): Observable<ReportResponse<CsatNpsReport>> {
    return this.http.get<ReportResponse<CsatNpsReport>>(`${this.baseUrl}/csat-nps`, {
      params: this.buildParams(filters),
    });
  }

  /** Relatório de volume de atendimento. */
  getChatVolume(filters: ReportFilter): Observable<ReportResponse<ChatVolumeReport>> {
    return this.http.get<ReportResponse<ChatVolumeReport>>(`${this.baseUrl}/chat-volume`, {
      params: this.buildParams(filters),
    });
  }

  /** Relatório de uso e custo de IA. */
  getAiUsageCost(filters: ReportFilter): Observable<ReportResponse<AiUsageReport>> {
    return this.http.get<ReportResponse<AiUsageReport>>(`${this.baseUrl}/ai-usage-cost`, {
      params: this.buildParams(filters),
    });
  }

  /** Relatório de faturamento. */
  getBilling(filters: ReportFilter): Observable<ReportResponse<BillingReport>> {
    return this.http.get<ReportResponse<BillingReport>>(`${this.baseUrl}/billing`, {
      params: this.buildParams(filters),
    });
  }

  /** Relatório de performance de produtos. */
  getProductPerformance(filters: ReportFilter): Observable<ReportResponse<ProductReport>> {
    return this.http.get<ReportResponse<ProductReport>>(`${this.baseUrl}/product-performance`, {
      params: this.buildParams(filters),
    });
  }

  /** Relatório de performance de automações. */
  getAutopilotPerformance(filters: ReportFilter): Observable<ReportResponse<AutopilotReport>> {
    return this.http.get<ReportResponse<AutopilotReport>>(`${this.baseUrl}/autopilot-performance`, {
      params: this.buildParams(filters),
    });
  }

  /** Relatório de atividade da equipe. */
  getTeamActivity(filters: ReportFilter): Observable<ReportResponse<TeamActivityReport>> {
    return this.http.get<ReportResponse<TeamActivityReport>>(`${this.baseUrl}/team-activity`, {
      params: this.buildParams(filters),
    });
  }

  /** Relatório de contatos CRM. */
  getContactCrm(filters: ReportFilter): Observable<ReportResponse<ContactCrmReport>> {
    return this.http.get<ReportResponse<ContactCrmReport>>(`${this.baseUrl}/contact-crm`, {
      params: this.buildParams(filters),
    });
  }

  /** Relatório de transcrição de mídia. */
  getMediaTranscription(
    filters: ReportFilter,
  ): Observable<ReportResponse<MediaTranscriptionReport>> {
    return this.http.get<ReportResponse<MediaTranscriptionReport>>(
      `${environment.apiUrl}/ai/usage/transcription`,
      { params: this.buildParams(filters) },
    );
  }

  /**
   * Exporta um relatório para download como CSV ou XLSX.
   * @param report Nome do relatório (slug)
   * @param format Formato de exportação
   * @param filters Filtros ativos
   */
  exportReport(report: string, format: 'csv' | 'xlsx', filters: ReportFilter): Observable<Blob> {
    const params = this.buildParams(filters).set('format', format);
    return this.http.get(`${this.baseUrl}/${report}/export`, {
      params,
      responseType: 'blob',
    });
  }
}
