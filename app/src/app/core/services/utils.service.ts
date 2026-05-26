import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';
import type { AddressData, CnpjData } from '@core/models/utils.model';
export type { AddressData, CnpjData } from '@core/models/utils.model';


/**
 * Utilitários para consultas externas (CNPJ, CEP) e formatação de documentos/telefones.
 *
 * Contexto: service HTTP para lookup de dados públicos e helpers de formatação.
 */
@Injectable({ providedIn: 'root' })
export class UtilsService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = environment.apiUrl;

  /**
   * Busca dados de empresa por CNPJ.
   *
   * @param cnpj - CNPJ limpo ou formatado
   * @returns Observable com dados da empresa
   */
  lookupCnpj(cnpj: string): Observable<{ data: { company_data: CnpjData } }> {
    const cleanCnpj = cnpj.replace(/\D/g, '');
    return this.http.get<{ data: { company_data: CnpjData } }>(
      `${this.baseUrl}/utils/cnpj/${cleanCnpj}`,
    );
  }

  /**
   * Busca dados de endereço por CEP.
   *
   * @param cep - CEP limpo ou formatado
   * @returns Observable com dados do endereço
   */
  lookupCep(cep: string): Observable<{ data: { address_data: AddressData } }> {
    const cleanCep = cep.replace(/\D/g, '');
    return this.http.get<{ data: { address_data: AddressData } }>(
      `${this.baseUrl}/utils/cep/${cleanCep}`,
    );
  }

  /**
   * Formata documento (CPF/CNPJ) para exibição.
   *
   * @param value - String com dígitos do documento
   * @returns Documento formatado ou '—' se vazio
   */
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

  /**
   * Formata número de telefone para exibição.
   *
   * @param value - String com dígitos do telefone
   * @returns Telefone formatado ou '—' se vazio
   */
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
