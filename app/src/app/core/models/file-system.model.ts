/**
 * Define um filtro de tipo de arquivo para diálogos de seleção de arquivo.
 *
 * Contexto: usado com `ElectronAPI.openFile()` para restringir os tipos
 * de arquivo exibidos no diálogo nativo de seleção.
 */
export interface FileFilter {
  /** Nome descritivo do tipo de arquivo (ex: "Imagens", "Documentos PDF"). */
  name: string;
  /** Lista de extensões sem o ponto (ex: ['png', 'jpg', 'gif']). */
  extensions: string[];
}
