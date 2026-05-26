import type { TenantAddressParts } from '@platform/models/tenant-address.model';

export type { TenantAddressParts } from '@platform/models/tenant-address.model';

/**
 * Remove todos os caracteres não numéricos de uma string.
 * @param value Valor a sanitizar
 * @returns Apenas os dígitos do valor
 */
export function digitsOnly(value: string | null | undefined): string {
  return (value ?? '').replace(/\D+/g, '');
}

/**
 * Normaliza uma sigla de UF para 2 letras maiúsculas.
 * @param value Sigla a normalizar
 * @returns Sigla em maiúsculas com no máximo 2 caracteres
 */
export function normalizeUf(value: string | null | undefined): string {
  return (value ?? '').trim().toUpperCase().slice(0, 2);
}

/**
 * Normaliza os dígitos de um CEP para exatamente 8 dígitos, aplicando zero à esquerda se necessário.
 * @param value CEP a normalizar
 * @returns String com 8 dígitos numéricos
 */
export function normalizeCepDigits(value: string | null | undefined): string {
  const digits = digitsOnly(value);

  if (digits.length === 7) {
    return digits.padStart(8, '0');
  }

  return digits.slice(0, 8);
}

/**
 * Formata um CEP para o padrão de formulário (XXXXX-XXX).
 * @param value CEP a formatar
 * @returns CEP formatado ou dígitos brutos se inválido
 */
export function formatCepForForm(value: string | null | undefined): string {
  const digits = normalizeCepDigits(value);
  if (digits.length !== 8) return digits;

  return `${digits.slice(0, 5)}-${digits.slice(5)}`;
}

/**
 * Constrói uma linha de endereço concatenando as partes não vazias separadas por vírgula.
 * @param parts Partes do endereço
 * @returns Linha de endereço ou undefined se todas as partes estiverem vazias
 */
export function buildAddressLine(parts: TenantAddressParts): string | undefined {
  const values = [parts.street, parts.number, parts.complement, parts.district]
    .map((value) => value?.trim())
    .filter((value): value is string => Boolean(value));

  if (values.length === 0) {
    return undefined;
  }

  return values.join(', ');
}
