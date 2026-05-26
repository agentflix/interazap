/** Estados possíveis de uma proposta ao longo do seu ciclo de vida. */
export type ProposalStatus = 'draft' | 'sent' | 'accepted' | 'rejected';

/**
 * Item de linha individual dentro de uma proposta.
 */
export interface ProposalItem {
  /** Identificador único (opcional para novos itens). */
  id?: string | number;
  /** Referência ao produto do CRM. */
  crm_product_id?: string | number | null;
  /** Nome do produto ou serviço. */
  name: string;
  /** Quantidade cotada. */
  quantity: number;
  /** Preço unitário antes do desconto. */
  unit_price: number;
  /** Desconto opcional (percentual ou valor). */
  discount?: number;
  /** Ordem de exibição do item. */
  position?: number;
  /** Total calculado da linha (quantidade * preço unitário - desconto). */
  total?: number;
}

/**
 * Proposta comercial enviada ao contato de uma negociação.
 */
export interface Proposal {
  /** Identificador único. */
  id: string | number;
  /** Negociação pai à qual esta proposta pertence. */
  crm_negotiation_id: string | number;
  /** Título da proposta. */
  title: string;
  /** Número sequencial da proposta. */
  number?: number | null;
  /** Status atual no ciclo de vida. */
  status: ProposalStatus;
  /** Data de validade da proposta. */
  valid_until?: string | null;
  /** Valor total calculado. */
  total?: number;
  /** Notas internas (não visíveis ao cliente). */
  notes?: string | null;
  /** Token de acesso público para visualização pelo cliente. */
  public_token?: string | null;
  /** URL do PDF gerado. */
  pdf_url?: string | null;
  /** Timestamp do envio ao cliente. */
  sent_at?: string | null;
  /** Timestamp da primeira visualização pelo cliente. */
  viewed_at?: string | null;
  /** Timestamp de aceite pelo cliente. */
  accepted_at?: string | null;
  /** Timestamp de rejeição pelo cliente. */
  rejected_at?: string | null;
  /** Itens de linha da proposta. */
  items?: ProposalItem[];
  /** Timestamp de criação. */
  created_at?: string;
  /** Timestamp da última atualização. */
  updated_at?: string;
}

/**
 * Payload para criação ou atualização de uma proposta.
 */
export interface ProposalPayload {
  /** Título da proposta. */
  title: string;
  /** Número sequencial da proposta. */
  number?: number | null;
  /** Status no ciclo de vida. */
  status?: ProposalStatus;
  /** Data de validade. */
  valid_until?: string | null;
  /** Notas internas. */
  notes?: string | null;
  /** Itens de linha. */
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
