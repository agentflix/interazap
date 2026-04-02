import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';

/** Possible states of a proposal throughout its lifecycle. */
export type ProposalStatus = 'draft' | 'sent' | 'accepted' | 'rejected';

/**
 * Individual line item within a proposal.
 */
export interface ProposalItem {
  /** Unique identifier (optional for new items) */
  id?: string | number;
  /** Reference to the CRM product */
  crm_product_id?: string | number | null;
  /** Product or service name */
  name: string;
  /** Quantity being quoted */
  quantity: number;
  /** Price per unit before discount */
  unit_price: number;
  /** Optional discount percentage or amount */
  discount?: number;
  /** Display order of the item */
  position?: number;
  /** Computed line total (quantity * unit_price - discount) */
  total?: number;
}

/**
 * A commercial proposal sent to a negotiation contact.
 */
export interface Proposal {
  /** Unique identifier */
  id: string | number;
  /** Parent negotiation this proposal belongs to */
  crm_negotiation_id: string | number;
  /** Proposal title/name */
  title: string;
  /** Sequential proposal number */
  number?: number | null;
  /** Current lifecycle status */
  status: ProposalStatus;
  /** Expiration date for the proposal */
  valid_until?: string | null;
  /** Computed total value */
  total?: number;
  /** Internal notes (not visible to client) */
  notes?: string | null;
  /** Public access token for client view */
  public_token?: string | null;
  /** URL to the generated PDF */
  pdf_url?: string | null;
  /** Timestamp when proposal was sent to client */
  sent_at?: string | null;
  /** Timestamp when client first viewed the proposal */
  viewed_at?: string | null;
  /** Timestamp when client accepted the proposal */
  accepted_at?: string | null;
  /** Timestamp when client rejected the proposal */
  rejected_at?: string | null;
  /** Line items in the proposal */
  items?: ProposalItem[];
  /** Creation timestamp */
  created_at?: string;
  /** Last update timestamp */
  updated_at?: string;
}

/**
 * Payload for creating or updating a proposal.
 */
export interface ProposalPayload {
  /** Proposal title/name */
  title: string;
  /** Sequential proposal number */
  number?: number | null;
  /** Lifecycle status */
  status?: ProposalStatus;
  /** Expiration date */
  valid_until?: string | null;
  /** Internal notes */
  notes?: string | null;
  /** Line items */
  items?: ProposalItem[];
}

/**
 * Standard paginated response envelope.
 */
export interface PaginatedResponse<T> {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

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
