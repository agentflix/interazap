/**
 * Modelos e tipos do componente de campo de telefone.
 */

/** Tamanhos disponíveis para o campo */
export type AfInputSize = 'sm' | 'md';

/** Entrada de código de país (DDI). */
export interface AfCountryCode {
  /** Nome do país */
  name: string;
  /** Código de discagem ISO (ex.: +55) */
  code: string;
  /** Código ISO de 2 letras do país */
  iso: string;
  /** Emoji da bandeira */
  flag: string;
}

export type Country = AfCountryCode;
