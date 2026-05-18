import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { map, type Observable } from 'rxjs';
import { environment } from '@env/environment';
import type { Called, CalledClosePayload, CalledCounts, CalledCreatePayload, CalledGetOptions, CalledListFilters, CalledListResponse, CalledMessage, CalledMessagesResponse, ChatTicketTransfer } from '@core/models/called.model';
export type { Called, CalledChannel, CalledClosePayload, CalledContactSummary, CalledCounts, CalledCreatePayload, CalledEvaluationSummary, CalledGetOptions, CalledListFilters, CalledListResponse, CalledMessage, CalledMessageSummary, CalledMessagesResponse, CalledResponse, CalledSentiment, CalledSortBy, CalledStatus, CalledUserSummary, ChatTicketTransfer } from '@core/models/called.model';



/** Evaluation summary for a ticket (CSAT) */

/** Payload for closing a ticket */

/** Message in a ticket conversation */

/** Paginated messages response */

interface CalledListEnvelope {
  data?:
    | Called[]
    | {
        data?: Called[];
        meta?: CalledListResponse['meta'];
        counts?: CalledCounts;
      };
  meta?: CalledListResponse['meta'];
  counts?: CalledCounts;
}

interface CalledMessagesEnvelope {
  data?: {
    messages?: CalledMessage[];
    meta?: CalledMessagesResponse['meta'];
  };
}

/**
 * Serviço para gestão de atendimentos (Tickets/Chamados).
 *
 * Responsável por listar, criar, abrir, transferir e encerrar atendimentos,
 * com contagens consolidadas por status retornadas na própria listagem.
 *
 * @class CalledService
 * @description Service central para operações de ciclo de vida de atendimentos.
 */
@Injectable({ providedIn: 'root' })
export class CalledService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/chat/tickets`;

  /**
   * Lista os atendimentos filtrados por diversos parâmetros.
   * @param filters Filtros de busca (status, canal, contato, instância, etc).
   * @returns {Observable<CalledListResponse>} Stream finito com a lista paginada de atendimentos.
   */
  list(filters: CalledListFilters = {}): Observable<CalledListResponse> {
    let params = new HttpParams();

    if (filters.search) params = params.set('search', filters.search);
    if (filters.status) params = params.set('status', filters.status);
    if (filters.channel) params = params.set('channel', filters.channel);
    if (filters.contact_id) params = params.set('contact_id', String(filters.contact_id));
    if (filters.instance_id) params = params.set('instance_id', String(filters.instance_id));
    if (filters.user_id) params = params.set('user_id', String(filters.user_id));
    if (filters.per_page) params = params.set('per_page', String(filters.per_page));
    if (filters.page) params = params.set('page', String(filters.page));
    if (filters.sentiment) params = params.set('sentiment', filters.sentiment);
    if (filters.sort_by) params = params.set('sort_by', filters.sort_by);
    if (filters.group_by_contact !== undefined) {
      params = params.set('group_by_contact', String(filters.group_by_contact));
    }
    if (filters.agent_id) params = params.set('agent_id', String(filters.agent_id));
    if (filters.from) params = params.set('from', filters.from);
    if (filters.to) params = params.set('to', filters.to);

    return this.http.get<CalledListEnvelope>(this.baseUrl, { params }).pipe(
      map((response) => {
        const fallbackMeta: CalledListResponse['meta'] = {
          current_page: filters.page ?? 1,
          total: 0,
          per_page: filters.per_page ?? 15,
          last_page: 1,
        };

        if (Array.isArray(response.data)) {
          return {
            data: response.data,
            meta: response.meta ?? fallbackMeta,
            counts: response.counts,
          };
        }

        const nested = response.data;

        return {
          data: nested?.data ?? [],
          meta: response.meta ?? nested?.meta ?? fallbackMeta,
          counts: response.counts ?? nested?.counts,
        };
      }),
    );
  }

  /**
   * Cria um novo atendimento (ticket) de forma manual.
   * @param payload Dados para criação do atendimento.
   * @returns {Observable<{ data: Called }>} Stream finito com o atendimento criado.
   */
  create(payload: CalledCreatePayload): Observable<{ data: Called }> {
    return this.http.post<{ data: Called }>(this.baseUrl, payload);
  }

  /**
   * Altera o status do atendimento para aberto (início de interação).
   * @param id Identificador do atendimento.
   * @returns {Observable<{ data: Called }>} Stream finito com o atendimento atualizado.
   */
  open(id: string | number): Observable<{ data: Called }> {
    return this.http.post<{ data: Called }>(`${this.baseUrl}/${id}/open`, {});
  }

  /**
   * Busca detalhes completos de um atendimento específico via ID.
   * @param id Identificador do atendimento.
   * @param options Opções adicionais de carregamento (ex.: incluir primeiras mensagens).
   * @returns {Observable<{ data: Called }>} Stream finito com os dados do atendimento.
   */
  get(id: string | number, options?: CalledGetOptions): Observable<{ data: Called }> {
    let params = new HttpParams();

    if (options?.includeMessages === true) {
      params = params.set('include_messages', 'true');
    }

    if (options?.messagesPerPage !== undefined) {
      params = params.set('messages_per_page', String(options.messagesPerPage));
    }

    return this.http.get<{ data: Called }>(`${this.baseUrl}/${id}`, { params });
  }

  /**
   * Transfere o atendimento para um novo agente ou departamento.
   * @param id Identificador do atendimento.
   * @param target Objeto contendo o ID do usuário de destino.
   * @returns {Observable<{ data: Called }>} Stream finito com o atendimento transferido.
   */
  transfer(
    id: string | number,
    target: { user_id?: string | number; department_id?: string | number },
  ): Observable<{ data: Called }> {
    return this.http.post<{ data: Called }>(`${this.baseUrl}/${id}/transfer`, target);
  }

  transferToUser(
    id: string | number,
    payload: { to_user_id: string | number; reason: string },
  ): Observable<{ data: ChatTicketTransfer }> {
    return this.http.post<{ data: ChatTicketTransfer }>(`${this.baseUrl}/${id}/transfers`, payload);
  }

  /**
   * Marca todas as mensagens de um atendimento como lidas pelo atendente.
   * @param id Identificador do atendimento.
   * @returns {Observable<void>} Stream finito.
   */
  markAsRead(id: string | number): Observable<void> {
    return this.http.post<void>(`${this.baseUrl}/${id}/read`, {});
  }

  /**
   * Encerra formalmente o atendimento (ticket).
   * @param id Identificador do atendimento.
   * @param payload Optional close payload with mode and reason.
   * @returns {Observable<{ data: Called }>} Stream finito com o atendimento encerrado.
   */
  close(id: string | number, payload: CalledClosePayload = {}): Observable<{ data: Called }> {
    return this.http.post<{ data: Called }>(`${this.baseUrl}/${id}/close`, payload);
  }

  /**
   * Returns the messages of a ticket with pagination.
   * @param ticketId Ticket identifier.
   * @param page Page number.
   * @returns {Observable<CalledMessagesResponse>} Paginated messages.
   */
  getMessages(ticketId: string | number, page = 1): Observable<CalledMessagesResponse> {
    const params = new HttpParams().set('page', String(page));

    return this.http
      .get<CalledMessagesResponse | CalledMessagesEnvelope>(
        `${this.baseUrl}/${ticketId}/messages`,
        {
          params,
        },
      )
      .pipe(
        map((response) => {
          if ('meta' in response && 'data' in response && Array.isArray(response.data)) {
            return response;
          }

          const envelope = response as CalledMessagesEnvelope;

          return {
            data: envelope.data?.messages ?? [],
            meta: envelope.data?.meta ?? {
              current_page: 1,
              total: 0,
              per_page: 20,
              last_page: 1,
            },
          };
        }),
      );
  }
}
