import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';
import type { PaginatedResponse, Proposal, ProposalPayload } from '@crm/models/proposal.model';
export type { PaginatedResponse, Proposal, ProposalItem, ProposalPayload, ProposalStatus } from '@crm/models/proposal.model';


/** Possible states of a proposal throughout its lifecycle. */

/**
 * Individual line item within a proposal.
 */

/**
 * A commercial proposal sent to a negotiation contact.
 */

/**
 * Payload for creating or updating a proposal.
 */

/**
 * Standard paginated response envelope.
 */

/**
 * Service for CRM proposal lifecycle management.
 *
 * Handles CRUD operations, sending, accepting/rejecting proposals,
 * and public-facing client views.
 *
 * @class CRMProposalService
 */
@Injectable({ providedIn: 'root' })
export class CRMProposalService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/crm/proposals`;

  /**
   * Lists all proposals attached to a specific negotiation.
   *
   * @param negotiationId - ID of the parent negotiation
   * @returns Observable with paginated proposal list
   */
  listByNegotiation(negotiationId: string | number): Observable<PaginatedResponse<Proposal>> {
    return this.http.get<PaginatedResponse<Proposal>>(
      `${environment.apiUrl}/crm/negotiations/${negotiationId}/proposals`,
    );
  }

  /**
   * Retrieves a single proposal by ID.
   *
   * @param id - Proposal identifier
   * @returns Observable with the proposal data
   */
  get(id: string | number): Observable<{ data: { proposal: Proposal } }> {
    return this.http.get<{ data: { proposal: Proposal } }>(`${this.baseUrl}/${id}`);
  }

  /**
   * Creates a new proposal for a negotiation.
   *
   * @param negotiationId - Parent negotiation ID
   * @param payload - Proposal data
   * @returns Observable with the created proposal
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
   * Updates an existing proposal.
   *
   * @param id - Proposal ID
   * @param payload - Fields to update
   * @returns Observable with the updated proposal
   */
  update(
    id: string | number,
    payload: ProposalPayload,
  ): Observable<{ data: { proposal: Proposal } }> {
    return this.http.put<{ data: { proposal: Proposal } }>(`${this.baseUrl}/${id}`, payload);
  }

  /**
   * Permanently deletes a proposal.
   *
   * @param id - Proposal ID
   * @returns Observable that completes on deletion
   */
  delete(id: string | number): Observable<void> {
    return this.http.delete<void>(`${this.baseUrl}/${id}`);
  }

  /**
   * Sends the proposal to the client via email with a public link.
   *
   * @param id - Proposal ID
   * @returns Observable with the updated proposal
   */
  send(id: string | number): Observable<{ data: { proposal: Proposal } }> {
    return this.http.post<{ data: { proposal: Proposal } }>(`${this.baseUrl}/${id}/send`, {});
  }

  /**
   * Creates a copy of an existing proposal as a new draft.
   *
   * @param id - Source proposal ID
   * @returns Observable with the new proposal
   */
  duplicate(id: string | number): Observable<{ data: { proposal: Proposal } }> {
    return this.http.post<{ data: { proposal: Proposal } }>(`${this.baseUrl}/${id}/duplicate`, {});
  }

  /**
   * Retrieves a proposal for public client view using the public token.
   *
   * @param token - Public access token from the proposal link
   * @returns Observable with the proposal (without sensitive internal data)
   */
  publicView(token: string): Observable<{ data: { proposal: Proposal } }> {
    return this.http.get<{ data: { proposal: Proposal } }>(
      `${environment.apiUrl}/crm/proposals/view/${token}`,
    );
  }

  /**
   * Marks a proposal as accepted via the public client interface.
   *
   * @param token - Public access token from the proposal link
   * @returns Observable with the updated proposal
   */
  publicAccept(token: string): Observable<{ data: { proposal: Proposal } }> {
    return this.http.post<{ data: { proposal: Proposal } }>(
      `${environment.apiUrl}/crm/proposals/${token}/accept`,
      {},
    );
  }

  /**
   * Marks a proposal as rejected via the public client interface.
   *
   * @param token - Public access token from the proposal link
   * @returns Observable with the updated proposal
   */
  publicReject(token: string): Observable<{ data: { proposal: Proposal } }> {
    return this.http.post<{ data: { proposal: Proposal } }>(
      `${environment.apiUrl}/crm/proposals/${token}/reject`,
      {},
    );
  }
}
