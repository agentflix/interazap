/**
 * Modelos e tipos do componente de upload de arquivo.
 */

/** Representa um arquivo em processo de upload. */
export interface AfUploadFile {
  /** Objeto File original */
  file: File;
  /** Nome de exibição */
  name: string;
  /** Tamanho do arquivo em bytes */
  size: number;
  /** Progresso do upload de 0 a 100 */
  progress: number;
  /** Estado do upload */
  status: 'pending' | 'uploading' | 'done' | 'cancelled' | 'error';
  /** ID interno */
  id: string;
}
