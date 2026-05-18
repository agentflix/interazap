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

export interface PaginatedResponse<T> {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}
