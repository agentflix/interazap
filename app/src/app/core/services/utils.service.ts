import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';

/** CNPJ lookup response data */
export interface CnpjData {
  cnpj: string;
  legal_name: string | null;
  trade_name: string | null;
  status: string | null;
  email: string | null;
  phone: string | null;
  street: string | null;
  number: string | null;
  complement: string | null;
  district: string | null;
  city: string | null;
  state: string | null;
  zip: string | null;
}

/** CEP lookup response data */
export interface AddressData {
  zip: string;
  street: string;
  complement: string;
  district: string;
  city: string;
  state: string;
  ibge: string;
}

/**
 * Utility service for external lookups (CNPJ, CEP) and formatting.
 */
@Injectable({ providedIn: 'root' })
export class UtilsService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = environment.apiUrl;

  /** Lookup company data by CNPJ. */
  lookupCnpj(cnpj: string): Observable<{ data: { company_data: CnpjData } }> {
    const cleanCnpj = cnpj.replace(/\D/g, '');
    return this.http.get<{ data: { company_data: CnpjData } }>(
      `${this.baseUrl}/utils/cnpj/${cleanCnpj}`,
    );
  }

  /** Lookup address data by CEP. */
  lookupCep(cep: string): Observable<{ data: { address_data: AddressData } }> {
    const cleanCep = cep.replace(/\D/g, '');
    return this.http.get<{ data: { address_data: AddressData } }>(
      `${this.baseUrl}/utils/cep/${cleanCep}`,
    );
  }

  /** Formats a document string (CPF/CNPJ) for display */
  formatDocument(value: string | undefined): string {
    if (!value) return '—';
    const digits = value.replace(/\D/g, '');

    if (digits.length === 11) {
      return digits.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
    }

    if (digits.length === 14) {
      return digits.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
    }

    return value;
  }

  /** Formats a phone number for display */
  formatPhone(value: string | undefined): string {
    if (!value) return '—';
    const digits = value.replace(/\D/g, '');

    if (digits.startsWith('55')) {
      const localNumber = digits.slice(2);

      if (localNumber.length === 11) {
        const formatted = localNumber.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
        return `+55 ${formatted}`;
      }

      if (localNumber.length === 10) {
        const formatted = localNumber.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
        return `+55 ${formatted}`;
      }
    }

    if (digits.length === 11) {
      return digits.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
    }

    if (digits.length === 10) {
      return digits.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
    }

    return value;
  }
}
