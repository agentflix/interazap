import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { map, type Observable } from 'rxjs';
import { environment } from '@env/environment';

export type CalledStatus = 'open' | 'pending' | 'in_progress' | 'closed';
export type CalledChannel = 'whatsapp' | 'internal' | 'external' | 'portal';
export type CalledSentiment = 'positive' | 'neutral' | 'negative' | 'critical';
export type CalledSortBy = 'last_message_at' | 'sentiment_score';

export interface CalledUserSummary {
  id: string | number;
  name: string;
  email?: string;
  department?: {
    id: string | number;
    name: string;
  } | null;
}

export interface CalledContactSummary {
  id: string | number;
  name: string;
  email?: string | null;
  phone?: string | null;
  whatsapp?: string | null;
  profile_picture_url?: string | null;
}

export interface CalledMessageSummary {
  id: string | number;
  content?: string | null;
  type?: string;
  created_at?: string | null;
}

/** Evaluation summary for a ticket (CSAT) */
export interface CalledEvaluationSummary {
  has_evaluation: boolean;
  rating?: number | null;
  comment?: string | null;
  submitted_at?: string | null;
}

export interface Called {
  id: string | number;
  company_id: string | number;
  user_id?: string | number | null;
  contact_id?: string | number | null;
  protocol?: string | null;
  profile_picture_url?: string | null;
  status: CalledStatus;
  is_bot_active?: boolean | null;
  channel: CalledChannel;
  subject?: string | null;
  notes?: string | null;
  queued_at?: string | null;
  started_at?: string | null;
  closed_at?: string | null;
  closed_by?: string | null;
  close_reason?: string | null;
  closed_mode?: 'normal' | 'forced' | null;
  wait_duration_seconds?: number | null;
  service_duration_seconds?: number | null;
  created_at?: string | null;
  updated_at?: string | null;
  user?: CalledUserSummary | null;
  assigned_user?: CalledUserSummary | null;
  contact?: CalledContactSummary | null;
  last_message?: CalledMessageSummary | null;
  evaluation?: CalledEvaluationSummary | null;
  unread_count?: number;
  sentiment?: CalledSentiment | null;
  sentiment_score?: number | null;
  sentiment_updated_at?: string | null;
}

export interface CalledListFilters {
  search?: string;
  status?: CalledStatus;
  channel?: CalledChannel;
  contact_id?: string | number;
  instance_id?: string | number;
  user_id?: string | number;
  agent_id?: string | number;
  from?: string;
  to?: string;
  per_page?: number;
  page?: number;
  sentiment?: CalledSentiment;
  sort_by?: CalledSortBy;
  group_by_contact?: boolean;
}

export interface CalledListResponse {
  data: Called[];
  meta: {
    current_page: number;
    total: number;
    per_page: number;
    last_page: number;
  };
  counts?: CalledCounts;
}

export interface CalledCounts {
  all: number;
  pending: number;
  open: number;
  closed: number;
  in_progress?: number;
}

export interface CalledCreatePayload {
  contact_id: string | number;
  instance_id?: string | number;
  channel?: CalledChannel;
  subject?: string;
  notes?: string;
}

export interface CalledGetOptions {
  includeMessages?: boolean;
  messagesPerPage?: number;
}

export interface CalledResponse {
  data: {
    called: Called;
  };
}

export interface ChatTicketTransfer {
  id: string | number;
  ticket_id: string | number;
  from_user_id: string | number | null;
  to_user_id: string | number;
  reason: string;
  status: string;
  transferred_at?: string | null;
}

/** Payload for closing a ticket */
export interface CalledClosePayload {
  mode?: 'normal' | 'forced';
  reason?: string;
}

/** Message in a ticket conversation */
export interface CalledMessage {
  id: string | number;
  ticket_id: string | number;
  content?: string | null;
  type?: string;
  direction: 'incoming' | 'outgoing';
  status?: string;
  created_at?: string | null;
  user?: CalledUserSummary | null;
}

/** Paginated messages response */
export interface CalledMessagesResponse {
  data: CalledMessage[];
  meta: {
    current_page: number;
    total: number;
    per_page: number;
    last_page: number;
  };
}

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
