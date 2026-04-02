/**
 * Modelos para resolução de tenant no domínio de billing.
 */

/** Tenant resolvido a partir do token de webhook de billing. */
export interface ResolvedTenant {
  tenant_id: string;
  name?: string | null;
}
