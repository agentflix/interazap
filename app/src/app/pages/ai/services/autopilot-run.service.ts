import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { type Observable, map } from 'rxjs';
import { environment } from '@env/environment';
import { type Paginated } from '@shared/types/pagination';
import { type AutopilotRun, type AutopilotRunPayload } from '@ai/models/ai.model';

// Re-export for backwards compatibility
export type { AutopilotRun, AutopilotRunPayload } from '@ai/models/ai.model';

/**
 * Serviço para gestão de execuções (Runs) do IA Autopilot.
 *
 * Permite listar históricos de execuções de roteiros e disparar
 * novas simulações ou execuções reais de playbooks.
 */
@Injectable({ providedIn: 'root' })
export class AutopilotRunService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/ai`;

  /**
   * Obtém uma lista paginada do histórico de execuções.
   * @param params Parâmetros de paginação
   * @returns Stream finito com a lista de execuções
   */
  list(params: { page?: number; per_page?: number } = {}): Observable<Paginated<AutopilotRun>> {
    let httpParams = new HttpParams();
    if (params.page !== undefined) httpParams = httpParams.set('page', String(params.page));
    if (params.per_page !== undefined)
      httpParams = httpParams.set('per_page', String(params.per_page));

    return this.http.get<Paginated<AutopilotRun>>(`${this.baseUrl}/runs`, { params: httpParams });
  }

  /**
   * Cria e executa uma run do Autopilot com contexto livre.
   * @param context Contexto usado pela execução
   * @returns Stream finito com a execução criada
   */
  create(context: Record<string, unknown>): Observable<AutopilotRun> {
    return this.http
      .post<{ data: AutopilotRun }>(`${this.baseUrl}/runs`, { context })
      .pipe(map((resp) => resp.data));
  }

  /**
   * Recupera os detalhes e resultados de uma execução específica.
   * @param id Identificador da execução
   * @returns Stream finito com os dados da execução
   */
  get(id: string): Observable<AutopilotRun> {
    return this.http
      .get<{ data: AutopilotRun }>(`${this.baseUrl}/runs/${id}`)
      .pipe(map((resp) => resp.data));
  }

  /**
   * Dispara a execução de um playbook específico.
   * @param playbookId Identificador do playbook a ser executado
   * @param payload Contexto e dados de entrada para a execução
   * @returns Stream finito com a execução iniciada
   */
  run(playbookId: string, payload: AutopilotRunPayload): Observable<AutopilotRun> {
    return this.http
      .post<{ data: AutopilotRun }>(`${this.baseUrl}/playbooks/${playbookId}/runs`, payload)
      .pipe(map((resp) => resp.data));
  }

  /**
   * Cancela uma execução em andamento.
   * @param id Identificador da execução
   * @returns Stream finito com o estado atualizado da execução
   */
  cancel(id: string): Observable<AutopilotRun> {
    return this.http
      .patch<{ data: AutopilotRun }>(`${this.baseUrl}/runs/${id}/cancel`, {})
      .pipe(map((resp) => resp.data));
  }
}
