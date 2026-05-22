import type { TenantAddressParts } from '@platform/models/tenant-address.model';

export type { TenantAddressParts } from '@platform/models/tenant-address.model';

export function digitsOnly(value: string | null | undefined): string {
  return (value ?? '').replace(/\D+/g, '');
}

export function normalizeUf(value: string | null | undefined): string {
  return (value ?? '').trim().toUpperCase().slice(0, 2);
}

export function normalizeCepDigits(value: string | null | undefined): string {
  const digits = digitsOnly(value);

  if (digits.length === 7) {
    return digits.padStart(8, '0');
  }

  return digits.slice(0, 8);
}

export function formatCepForForm(value: string | null | undefined): string {
  const digits = normalizeCepDigits(value);
  if (digits.length !== 8) return digits;

  return `${digits.slice(0, 5)}-${digits.slice(5)}`;
}

export function buildAddressLine(parts: TenantAddressParts): string | undefined {
  const values = [parts.street, parts.number, parts.complement, parts.district]
    .map((value) => value?.trim())
    .filter((value): value is string => Boolean(value));

  if (values.length === 0) {
    return undefined;
  }

  return values.join(', ');
}
