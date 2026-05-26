import type { PaginatedResponse } from '@core/models/pagination.model';

/**
 * Representa um produto ou serviço do catálogo do CRM.
 *
 * Contexto: usado no módulo CRM para vincular produtos/serviços a negociações.
 * Compartilhado entre `crm-product-service.service` e `product-service.service`.
 *
 * @remarks
 * Há dois serviços que usam este modelo com pequenas diferenças na tipagem
 * de resposta. Quando os serviços forem unificados, as definições inline
 * de `product-service.service` devem ser migradas para este modelo canônico.
 */
export interface ProductService {
  id: string;
  company_id: string;
  /** ID da categoria do produto/serviço. */
  category_id?: string;
  /** Código interno de identificação do produto. */
  code?: string;
  name: string;
  description?: string;
  /** Tipo: produto físico ou serviço prestado. */
  type: 'product' | 'service';
  /** Preço de venda. */
  price?: number;
  /** Custo de aquisição/produção. */
  cost?: number;
  /** Unidade de medida (ex: un, kg, hr). */
  unit?: string;
  /** Quantidade em estoque. */
  stock_quantity?: number;
  /** Quantidade mínima de estoque para alerta. */
  min_stock?: number;
  is_active: boolean;
  /** Indica se o produto está em destaque. */
  is_featured?: boolean;
  /** Indica se o controle de estoque está habilitado. */
  track_stock?: boolean;
  stock?: number;
  /** URL da imagem do produto. */
  image?: string;
  /** Atributos personalizados do produto (chave-valor). */
  attributes?: Record<string, unknown>;
  category?: { id: string; name: string };
  created_at: string;
  updated_at: string;
}

/**
 * Filtros para listagem de produtos e serviços.
 */
export interface ProductServiceFilters {
  search?: string;
  type?: 'product' | 'service';
  is_active?: boolean;
  category_id?: string;
  sort_by?: string;
  sort_dir?: string;
  per_page?: number;
  page?: number;
}

/**
 * Resposta paginada da API para listagem de produtos e serviços.
 */
export interface ProductServiceListResponse extends PaginatedResponse<ProductService> {
  success: boolean;
}

/**
 * Resposta da API para um único produto ou serviço.
 */
export interface ProductServiceResponse {
  success: boolean;
  data: ProductService;
}
