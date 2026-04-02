import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';

export interface NegotiationTaskUser {
  id: string | number;
  name: string;
  avatar?: string | null;
}

export interface NegotiationTask {
  id: string | number;
  negotiation_id: string | number;
  title: string;
  description?: string | null;
  action_type?: string | null;
  due_date?: string | null;
  start_time?: string | null;
  end_time?: string | null;
  status?: string | null;
  reminder_at?: string | null;
  add_to_agenda?: boolean;
  agenda_event_id?: string | number | null;
  notify_ui?: boolean;
  notify_email?: boolean;
  notify_push?: boolean;
  notify_whatsapp?: boolean;
  is_completed: boolean;
  completed_at?: string | null;
  user_id?: string | number | null;
  assigned_to?: string | number | null;
  priority?: 'low' | 'medium' | 'high';
  user?: NegotiationTaskUser;
  negotiation?: {
    id: string | number;
    title: string;
    crm_company?: {
      id: string | number;
      name: string;
    } | null;
  } | null;
  created_at?: string;
  updated_at?: string;
}

export interface NegotiationTaskPayload {
  title: string;
  description?: string;
  action_type?: string;
  due_date?: string | null;
  start_time?: string | null;
  end_time?: string | null;
  status?: string;
  add_to_agenda?: boolean;
  notify_ui?: boolean;
  notify_email?: boolean;
  notify_push?: boolean;
  notify_whatsapp?: boolean;
}

@Injectable({ providedIn: 'root' })
export class NegotiationTaskService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/crm/negotiations`;

  /**
   * Lista todas as tarefas vinculadas a uma negociacao.
   *
   * @param negotiationId - Identificador da negociacao
   * @returns Observable com array de tarefas
   */
  list(negotiationId: string | number): Observable<{ data: { tasks: NegotiationTask[] } }> {
    return this.http.get<{ data: { tasks: NegotiationTask[] } }>(
      `${this.baseUrl}/${negotiationId}/tasks`,
    );
  }

  /**
   * Lista tarefas atribuidas ao usuario autenticado.
   *
   * @param filters - Filtros opcionais como status
   * @returns Observable com array de tarefas do usuario
   */
  listForUser(
    filters: { status?: string } = {},
  ): Observable<{ data: { tasks: NegotiationTask[] } }> {
    let params = new HttpParams();
    if (filters.status) params = params.set('status', filters.status);
    return this.http.get<{ data: { tasks: NegotiationTask[] } }>(
      `${environment.apiUrl}/crm/negotiations/tasks/user`,
      { params },
    );
  }

  /**
   * Cria uma nova tarefa vinculada a uma negociacao.
   *
   * @param negotiationId - Identificador da negociacao
   * @param payload - Dados da tarefa (titulo, descricao, due_date, etc)
   * @returns Observable com a tarefa criada
   */
  create(
    negotiationId: string | number,
    payload: NegotiationTaskPayload,
  ): Observable<{ data: NegotiationTask }> {
    return this.http.post<{ data: NegotiationTask }>(
      `${this.baseUrl}/${negotiationId}/tasks`,
      payload,
    );
  }

  /**
   * Atualiza uma tarefa existente.
   *
   * @param negotiationId - Identificador da negociacao
   * @param taskId - Identificador da tarefa
   * @param payload - Dados a serem atualizados
   * @returns Observable com a tarefa atualizada
   */
  update(
    negotiationId: string | number,
    taskId: string | number,
    payload: NegotiationTaskPayload,
  ): Observable<{ data: NegotiationTask }> {
    return this.http.put<{ data: NegotiationTask }>(
      `${this.baseUrl}/${negotiationId}/tasks/${taskId}`,
      payload,
    );
  }

  /**
   * Alterna o status de conclusao de uma tarefa (completa/pendente).
   *
   * @param negotiationId - Identificador da negociacao
   * @param taskId - Identificador da tarefa
   * @returns Observable com a tarefa atualizada
   */
  toggle(
    negotiationId: string | number,
    taskId: string | number,
  ): Observable<{ data: NegotiationTask }> {
    return this.http.patch<{ data: NegotiationTask }>(
      `${this.baseUrl}/${negotiationId}/tasks/${taskId}/toggle`,
      {},
    );
  }

  /**
   * Atualiza o status textual de uma tarefa.
   *
   * @param negotiationId - Identificador da negociacao
   * @param taskId - Identificador da tarefa
   * @param status - Novo status textual
   * @returns Observable com a tarefa atualizada
   */
  updateStatus(
    negotiationId: string | number,
    taskId: string | number,
    status: string,
  ): Observable<{ data: NegotiationTask }> {
    return this.http.patch<{ data: NegotiationTask }>(
      `${this.baseUrl}/${negotiationId}/tasks/${taskId}/status`,
      { status },
    );
  }

  /**
   * Remove uma tarefa de uma negociacao.
   *
   * @param negotiationId - Identificador da negociacao
   * @param taskId - Identificador da tarefa
   * @returns Observable vazio indicando sucesso
   */
  delete(negotiationId: string | number, taskId: string | number): Observable<void> {
    return this.http.delete<void>(`${this.baseUrl}/${negotiationId}/tasks/${taskId}`);
  }
}
