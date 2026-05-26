/**
 * Dados retornados pela consulta de CNPJ na Receita Federal.
 *
 * Contexto: usado nos formulários de cadastro de empresa para preencher
 * automaticamente os campos a partir do CNPJ informado.
 */
export interface CnpjData {
  /** CNPJ consultado (somente dígitos). */
  cnpj: string;
  /** Razão social da empresa. */
  legal_name: string | null;
  /** Nome fantasia da empresa. */
  trade_name: string | null;
  /** Situação cadastral (ex: Ativa, Baixada). */
  status: string | null;
  email: string | null;
  phone: string | null;
  street: string | null;
  number: string | null;
  complement: string | null;
  /** Bairro. */
  district: string | null;
  city: string | null;
  /** UF (estado). */
  state: string | null;
  /** CEP (somente dígitos). */
  zip: string | null;
}

/**
 * Dados de endereço retornados pela consulta de CEP (ViaCEP ou similar).
 *
 * Contexto: usado nos formulários de endereço para preencher automaticamente
 * os campos a partir do CEP informado.
 */
export interface AddressData {
  /** CEP consultado (somente dígitos). */
  zip: string;
  /** Logradouro. */
  street: string;
  complement: string;
  /** Bairro. */
  district: string;
  city: string;
  /** UF (estado). */
  state: string;
  /** Código IBGE do município. */
  ibge: string;
}
