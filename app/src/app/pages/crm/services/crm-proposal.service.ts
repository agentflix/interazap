import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';
import type { PaginatedResponse, Proposal, ProposalPayload } from '@crm/models/proposal.model';
export type { PaginatedResponse, Proposal, ProposalItem, ProposalPayload, ProposalStatus } from '@crm/models/proposal.model';


/**
 * Serviço para gerenciamento do ciclo de vida de propostas do CRM.
 *
 * Responsável por operações CRUD, envio, aceite/rejeição de propostas
 * e visualizações públicas para clientes.
 */
@Injectable({ providedIn: 'root' })
export class CRMProposalService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/crm/proposals`;

  /**
   * Lista todas as propostas vinculadas a uma negociação específica.
   * @param negotiationId ID da negociação pai
   * @returns Observable com lista paginada de propostas
   */
  listByNegotiation(negotiationId: string | number): Observable<PaginatedResponse<Proposal>> {
    return this.http.get<PaginatedResponse<Proposal>>(
      `${environment.apiUrl}/crm/negotiations/${negotiationId}/proposals`,
    );
  }

  /**
   * Recupera uma proposta específica por ID.
   * @param id Identificador da proposta
   * @returns Observable com os dados da proposta
   */
  get(id: string | number): Observable<{ data: { proposal: Proposal } }> {
    return this.http.get<{ data: { proposal: Proposal } }>(`${this.baseUrl}/${id}`);
  }

  /**
   * Cria uma nova proposta para uma negociação.
   * @param negotiationId ID da negociação pai
   * @param payload Dados da proposta
   * @returns Observable com a proposta criada
   */
  create(
    negotiationId: string | number,
    payload: ProposalPayload,
  ): Observable<{ data: { proposal: Proposal } }> {
    return this.http.post<{ data: { proposal: Proposal } }>(
      `${environment.apiUrl}/crm/negotiations/${negotiationId}/proposals`,
      payload,
    );
  }

  /**
   * Atualiza uma proposta existente.
   * @param id ID da proposta
   * @param payload Campos a atualizar
   * @returns Observable com a proposta atualizada
   */
  update(
    id: string | number,
    payload: ProposalPayload,
  ): Observable<{ data: { proposal: Proposal } }> {
    return this.http.put<{ data: { proposal: Proposal } }>(`${this.baseUrl}/${id}`, payload);
  }

  /**
   * Exclui permanentemente uma proposta.
   * @param id ID da proposta
   * @returns Observable que completa após exclusão
   */
  delete(id: string | number): Observable<void> {
    return this.http.delete<void>(`${this.baseUrl}/${id}`);
  }

  /**
   * Envia a proposta ao cliente por e-mail com link público.
   * @param id ID da proposta
   * @returns Observable com a proposta atualizada
   */
  send(id: string | number): Observable<{ data: { proposal: Proposal } }> {
    return this.http.post<{ data: { proposal: Proposal } }>(`${this.baseUrl}/${id}/send`, {});
  }

  /**
   * Cria uma cópia de uma proposta existente como novo rascunho.
   * @param id ID da proposta origem
   * @returns Observable com a nova proposta
   */
  duplicate(id: string | number): Observable<{ data: { proposal: Proposal } }> {
    return this.http.post<{ data: { proposal: Proposal } }>(`${this.baseUrl}/${id}/duplicate`, {});
  }

  /**
   * Recupera uma proposta para visualização pública do cliente usando o token público.
   * @param token Token de acesso público do link da proposta
   * @returns Observable com a proposta (sem dados internos sensíveis)
   */
  publicView(token: string): Observable<{ data: { proposal: Proposal } }> {
    return this.http.get<{ data: { proposal: Proposal } }>(
      `${environment.apiUrl}/crm/proposals/view/${token}`,
    );
  }

  /**
   * Marca uma proposta como aceita pela interface pública do cliente.
   * @param token Token de acesso público do link da proposta
   * @returns Observable com a proposta atualizada
   */
  publicAccept(token: string): Observable<{ data: { proposal: Proposal } }> {
    return this.http.post<{ data: { proposal: Proposal } }>(
      `${environment.apiUrl}/crm/proposals/${token}/accept`,
      {},
    );
  }

  /**
   * Marca uma proposta como rejeitada pela interface pública do cliente.
   * @param token Token de acesso público do link da proposta
   * @returns Observable com a proposta atualizada
   */
  publicReject(token: string): Observable<{ data: { proposal: Proposal } }> {
    return this.http.post<{ data: { proposal: Proposal } }>(
      `${environment.apiUrl}/crm/proposals/${token}/reject`,
      {},
    );
  }
}
