export interface TenantAddressParts {
  street?: string | null;
  number?: string | null;
  complement?: string | null;
  district?: string | null;
}

export function digitsOnly(value: string | null | undefined): string {
  return (value ?? '').replace(/\D+/g, '');
}

export function normalizeUf(value: string | null | undefined): string {
  return (value ?? '').trim().toUpperCase().slice(0, 2);
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
