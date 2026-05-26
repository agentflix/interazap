import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';
import type {
  OpeningHour,
  OpeningHourResponse,
  OpeningHourListResponse,
  BulkUpdateOpeningHoursRequest,
} from '@core/models/opening-hour.model';

/**
 * Gerencia os horários de atendimento do tenant.
 *
 * @remarks
 * Fornece operações de CRUD para definir agendas semanais de horário comercial
 * e verificar se o negócio está aberto no momento.
 *
 * @example
 * ```typescript
 * const service = inject(OpeningHourService);
 * service.isOpen().subscribe();
 * ```
 */
@Injectable({
  providedIn: 'root',
})
export class OpeningHourService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = `${environment.apiUrl}/opening-hours`;

  /**
   * Lista todas as entradas de horário de atendimento do tenant.
   *
   * @returns Observable com a lista de horários
   */
  list(): Observable<OpeningHourListResponse> {
    return this.http.get<OpeningHourListResponse>(this.apiUrl);
  }

  /**
   * Retorna uma entrada de horário de atendimento pelo ID.
   *
   * @param id - Identificador do horário
   * @returns Observable com os dados do horário
   */
  show(id: string): Observable<OpeningHourResponse> {
    return this.http.get<OpeningHourResponse>(`${this.apiUrl}/${id}`);
  }

  /**
   * Cria uma nova entrada de horário de atendimento.
   *
   * @param data - Dados do horário (day_of_week, open_time, close_time, is_active)
   * @returns Observable com o horário criado
   */
  create(data: Partial<OpeningHour>): Observable<OpeningHourResponse> {
    return this.http.post<OpeningHourResponse>(this.apiUrl, data);
  }

  /**
   * Atualiza uma entrada de horário de atendimento existente.
   *
   * @param id - Identificador do horário
   * @param data - Dados parciais do horário para atualização
   * @returns Observable com o horário atualizado
   */
  update(id: string, data: Partial<OpeningHour>): Observable<OpeningHourResponse> {
    return this.http.put<OpeningHourResponse>(`${this.apiUrl}/${id}`, data);
  }

  /**
   * Exclui uma entrada de horário de atendimento.
   *
   * @param id - Identificador do horário
   * @returns Observable que completa após a exclusão
   */
  delete(id: string): Observable<null> {
    return this.http.delete<null>(`${this.apiUrl}/${id}`);
  }

  /**
   * Realiza atualização em lote de todos os horários (substitui todas as entradas).
   *
   * @param data - Requisição de atualização em lote com o array completo de horários
   * @returns Observable com a lista atualizada de horários
   */
  bulkUpdate(data: BulkUpdateOpeningHoursRequest): Observable<OpeningHourListResponse> {
    return this.http.put<OpeningHourListResponse>(`${this.apiUrl}/bulk`, data);
  }

  /**
   * Verifica se o negócio está aberto com base nos horários configurados.
   *
   * @returns Observable com flag `is_open`, dia atual e hora atual
   */
  isOpen(): Observable<{
    success: boolean;
    data: { is_open: boolean; current_day: number; current_time: string };
  }> {
    return this.http.get<{
      success: boolean;
      data: { is_open: boolean; current_day: number; current_time: string };
    }>(`${this.apiUrl}/is-open`);
  }
}
