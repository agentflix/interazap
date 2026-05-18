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

export interface AddressData {
  zip: string;
  street: string;
  complement: string;
  district: string;
  city: string;
  state: string;
  ibge: string;
}
