import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import type { Observable } from 'rxjs';
import { map, switchMap } from 'rxjs/operators';
import { environment } from '@env/environment';

export interface NegotiationContactLink {
  id: string | number;
  negotiation_id: string | number;
  contact_id: string | number;
  role?: string | null;
  is_primary?: boolean;
  notes?: string | null;
  contact?: {
    id: string | number;
    name: string;
    email?: string | null;
    phone?: string | null;
    whatsapp?: string | null;
    crm_company_id?: string | number | null;
  } | null;
  created_at?: string | null;
}

export interface NegotiationContactPayload {
  contact_id?: string | number;
  role?: string;
  is_primary?: boolean;
  notes?: string | null;
}

interface NegotiationResponse {
  id: string | number;
  title: string;
  amount: number;
  status: string;
  crm_negotiation_funnel_id: string | number;
  crm_negotiation_funnel_step_id: string | number;
  crm_contact_id: string | number;
  crm_company_id?: string | number | null;
  expected_close?: string | null;
  contact?: {
    id: string | number;
    name: string;
    email?: string | null;
    phone?: string | null;
    whatsapp?: string | null;
    crm_company_id?: string | number | null;
  } | null;
}

@Injectable({ providedIn: 'root' })
export class NegotiationContactService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/crm/negotiations`;

  /**
   * Lista contatos vinculados a uma negociacao.
   *
   * @param negotiationId - Identificador da negociacao
   * @returns Observable com array de contatos associados
   */
  list(
    negotiationId: string | number,
  ): Observable<{ data: { contacts: NegotiationContactLink[] } }> {
    return this.getNegotiation(negotiationId).pipe(
      map((negotiation) => ({
        data: {
          contacts: negotiation.crm_contact_id
            ? [
                {
                  id: String(negotiation.crm_contact_id),
                  negotiation_id: negotiation.id,
                  contact_id: negotiation.crm_contact_id,
                  role: 'other',
                  is_primary: true,
                  notes: null,
                  contact: negotiation.contact ?? null,
                },
              ]
            : [],
        },
      })),
    );
  }

  /**
   * Cria ou associa um contato a uma negociacao.
   *
   * @param negotiationId - Identificador da negociacao
   * @param payload - Dados do vinculo (contact_id, role, is_primary, notes)
   * @returns Observable com o contato vinculado
   */
  create(
    negotiationId: string | number,
    payload: NegotiationContactPayload,
  ): Observable<{ data: { contact: NegotiationContactLink } }> {
    return this.updateNegotiationContact(negotiationId, payload.contact_id, payload).pipe(
      map((link) => ({ data: { contact: link } })),
    );
  }

  /**
   * Atualiza um vinculo existente entre contato e negociacao.
   *
   * @param negotiationId - Identificador da negociacao
   * @param linkId - Identificador do vinculo
   * @param payload - Dados a serem atualizados
   * @returns Observable com o vinculo atualizado
   */
  update(
    negotiationId: string | number,
    linkId: string | number,
    payload: NegotiationContactPayload,
  ): Observable<{ data: { contact: NegotiationContactLink } }> {
    return this.updateNegotiationContact(negotiationId, payload.contact_id ?? linkId, payload).pipe(
      map((link) => ({ data: { contact: link } })),
    );
  }

  /**
   * Remove o vinculo entre um contato e uma negociacao (define contact_id como null).
   *
   * @param negotiationId - Identificador da negociacao
   * @param linkId - Identificador do vinculo
   * @returns Observable vazio indicando sucesso
   */
  delete(negotiationId: string | number, linkId: string | number): Observable<void> {
    return this.getNegotiation(negotiationId).pipe(
      switchMap((negotiation) =>
        this.http.put<void>(`${this.baseUrl}/${negotiationId}`, {
          title: negotiation.title,
          amount: negotiation.amount,
          status: negotiation.status,
          crm_negotiation_funnel_id: negotiation.crm_negotiation_funnel_id,
          crm_negotiation_funnel_step_id: negotiation.crm_negotiation_funnel_step_id,
          crm_contact_id: null,
          crm_company_id: negotiation.crm_company_id ?? null,
          expected_close: negotiation.expected_close ?? null,
        }),
      ),
    );
  }

  private getNegotiation(negotiationId: string | number): Observable<NegotiationResponse> {
    return this.http
      .get<{
        data: NegotiationResponse | { negotiation: NegotiationResponse };
      }>(`${this.baseUrl}/${negotiationId}`)
      .pipe(
        map((response) => {
          const data = response.data;
          return 'negotiation' in data ? data.negotiation : data;
        }),
      );
  }

  private updateNegotiationContact(
    negotiationId: string | number,
    contactId: string | number | undefined,
    payload: NegotiationContactPayload,
  ): Observable<NegotiationContactLink> {
    return this.getNegotiation(negotiationId).pipe(
      switchMap((negotiation) =>
        this.http
          .put<{ data: NegotiationResponse | { negotiation: NegotiationResponse } }>(
            `${this.baseUrl}/${negotiationId}`,
            {
              title: negotiation.title,
              amount: negotiation.amount,
              status: negotiation.status,
              crm_negotiation_funnel_id: negotiation.crm_negotiation_funnel_id,
              crm_negotiation_funnel_step_id: negotiation.crm_negotiation_funnel_step_id,
              crm_contact_id: contactId ?? negotiation.crm_contact_id,
              crm_company_id: negotiation.crm_company_id ?? null,
              expected_close: negotiation.expected_close ?? null,
            },
          )
          .pipe(
            map((response) => {
              const data = response.data;
              const updated = 'negotiation' in data ? data.negotiation : data;

              return {
                id: String(updated.crm_contact_id),
                negotiation_id: updated.id,
                contact_id: updated.crm_contact_id,
                role: payload.role ?? 'other',
                is_primary: payload.is_primary ?? true,
                notes: payload.notes ?? null,
                contact: updated.contact ?? null,
              };
            }),
          ),
      ),
    );
  }
}
