/**
 * Partes individuais de um endereço de tenant usadas para montar a linha de endereço completa.
 */
export interface TenantAddressParts {
  street?: string | null;
  number?: string | null;
  complement?: string | null;
  district?: string | null;
}
