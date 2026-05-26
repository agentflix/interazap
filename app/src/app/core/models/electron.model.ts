/**
 * Interface de acesso às APIs nativas do Electron expostas via `contextBridge`.
 *
 * Contexto: disponível globalmente em `window.electronAPI` quando o aplicativo
 * está rodando como desktop via Electron. Na versão web, este objeto não existe.
 */
export interface ElectronAPI {
  // Controle de janela
  /** Minimiza a janela principal. */
  minimizeWindow: () => void;
  /** Maximiza ou restaura a janela principal. */
  maximizeWindow: () => void;
  /** Fecha a janela principal. */
  closeWindow: () => void;
  /** Retorna se a janela está maximizada. */
  isMaximized: () => Promise<boolean>;

  // Sistema
  /** Retorna a versão do aplicativo. */
  getAppVersion: () => Promise<string>;
  /** Retorna a plataforma do sistema operacional (win32, darwin, linux). */
  getPlatform: () => string;
  /** Abre uma URL no navegador padrão do sistema. */
  openExternal: (url: string) => Promise<boolean>;

  // Notificações
  /** Exibe uma notificação nativa do sistema operacional. */
  showNotification: (title: string, body: string) => void;

  // Sistema de arquivos
  /** Salva um arquivo no sistema de arquivos local. Retorna o caminho salvo ou null. */
  saveFile: (data: ArrayBuffer, filename: string) => Promise<string | null>;
  /** Abre o diálogo de seleção de arquivo. Retorna caminho e conteúdo ou null. */
  openFile: (options?: { filters?: { name: string; extensions: string[] }[] }) => Promise<{
    path: string;
    content: ArrayBuffer;
  } | null>;
  /** Abre uma pasta no explorador de arquivos do sistema. */
  openFolder: (path: string) => Promise<string>;

  // Captura de tela
  /** Retorna as fontes disponíveis para captura de tela. */
  getScreenSources: () => Promise<ScreenSource[]>;

  // Atualizações
  /** Verifica se há atualizações disponíveis. Retorna informações da versão ou null. */
  checkForUpdates: () => Promise<UpdateInfo | null>;
  /** Inicia o download da atualização disponível. */
  downloadUpdate: () => Promise<boolean>;
  /** Instala a atualização baixada e reinicia o aplicativo. */
  installUpdate: () => void;

  // Eventos
  /** Registra um callback para mudanças no estado de maximização da janela. */
  onMaximizedChange: (callback: (isMaximized: boolean) => void) => void;
}

/**
 * Representa uma fonte de captura de tela disponível no Electron.
 *
 * Contexto: retornado por `ElectronAPI.getScreenSources()` para seleção
 * de tela ou janela a ser compartilhada/capturada.
 */
export interface ScreenSource {
  id: string;
  name: string;
  /** Miniatura da fonte em formato base64. */
  thumbnail: string;
  display_id: string;
}

/**
 * Informações sobre uma atualização disponível do aplicativo Electron.
 */
export interface UpdateInfo {
  /** Versão da atualização disponível. */
  version: string;
  /** Data de lançamento da versão. */
  releaseDate: string;
}
