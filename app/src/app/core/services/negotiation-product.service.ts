import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { type Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { environment } from '@env/environment';
import type { NegotiationProductItem, NegotiationProductPayload } from '@core/models/negotiation.model';
export type { NegotiationProductItem, NegotiationProductPayload } from '@core/models/negotiation.model';



interface NegotiationProductApiItem {
  id: string | number;
  crm_negotiation_id: string | number;
  crm_product_id?: string | number | null;
  name?: string | null;
  quantity: number;
  unit_price: number;
  total?: number;
}

/**
 * Gerencia produtos de uma negociação (itens de linha da negociação).
 *
 * @remarks
 * Fornece operações de CRUD para produtos de negociação, incluindo
 * quantidade, precificação e gerenciamento de desconto.
 */
@Injectable({ providedIn: 'root' })
export class NegotiationProductService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/crm/negotiations`;

  /**
   * Lista todos os produtos de uma negociação.
   *
   * @param negotiationId - Identificador da negociação
   * @returns Observable com o array de produtos e o total calculado
   */
  list(
    negotiationId: string | number,
  ): Observable<{ data: { products: NegotiationProductItem[]; total?: number } }> {
    return this.http
      .get<{ data: NegotiationProductApiItem[] }>(`${this.baseUrl}/${negotiationId}/products`)
      .pipe(
        map((response) => {
          const products = (response.data ?? []).map((item) => this.mapItem(item));
          const total = products.reduce((sum, item) => sum + (item.total ?? 0), 0);
          return { data: { products, total } };
        }),
      );
  }

  /**
   * Adiciona um produto a uma negociação.
   *
   * @param negotiationId - Identificador da negociação
   * @param payload - Dados do produto (product_id, quantidade, preço, etc.)
   * @returns Observable com o item de produto criado
   */
  create(
    negotiationId: string | number,
    payload: NegotiationProductPayload,
  ): Observable<{ data: NegotiationProductItem }> {
    return this.http
      .post<{ data: NegotiationProductApiItem }>(`${this.baseUrl}/${negotiationId}/products`, {
        crm_product_id: payload.crm_product_id ?? payload.product_id,
        name: payload.name ?? 'Item',
        quantity: payload.quantity,
        unit_price: payload.unit_price ?? payload.price,
      })
      .pipe(map((response) => ({ data: this.mapItem(response.data) })));
  }

  /**
   * Atualiza um item de produto em uma negociação.
   *
   * @param negotiationId - Identificador da negociação
   * @param productId - Identificador do item de produto
   * @param payload - Dados atualizados do produto
   * @returns Observable com o item de produto atualizado
   */
  update(
    negotiationId: string | number,
    productId: string | number,
    payload: NegotiationProductPayload,
  ): Observable<{ data: NegotiationProductItem }> {
    return this.http
      .put<{ data: NegotiationProductApiItem }>(
        `${environment.apiUrl}/crm/negotiation-products/${productId}`,
        {
          crm_product_id: payload.crm_product_id ?? payload.product_id,
          name: payload.name ?? 'Item',
          quantity: payload.quantity,
          unit_price: payload.unit_price ?? payload.price,
        },
      )
      .pipe(map((response) => ({ data: this.mapItem(response.data) })));
  }

  /**
   * Remove um produto de uma negociação.
   *
   * @param negotiationId - Identificador da negociação
   * @param productId - Identificador do item de produto
   * @returns Observable que completa após a exclusão
   */
  delete(negotiationId: string | number, productId: string | number): Observable<void> {
    return this.http.delete<void>(`${environment.apiUrl}/crm/negotiation-products/${productId}`);
  }

  private mapItem(item: NegotiationProductApiItem): NegotiationProductItem {
    return {
      id: item.id,
      negotiation_id: item.crm_negotiation_id,
      product_id: item.crm_product_id ?? '',
      quantity: item.quantity,
      price: item.unit_price,
      discount: 0,
      subtotal: item.quantity * item.unit_price,
      total: item.total ?? item.quantity * item.unit_price,
      product:
        item.name !== null && item.name !== undefined
          ? {
              id: item.crm_product_id ?? '',
              name: item.name,
              price: item.unit_price,
            }
          : null,
    };
  }
}
