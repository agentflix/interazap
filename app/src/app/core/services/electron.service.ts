import { Injectable } from '@angular/core';
import type { ElectronAPI, ScreenSource, UpdateInfo } from '@core/models/electron.model';
export type { ElectronAPI, ScreenSource, UpdateInfo } from '@core/models/electron.model';


declare global {
  interface Window {
    electronAPI: ElectronAPI;
  }
}


/**
 * Servico que abstrai a API do Electron (IPC bridge).
 * Disponibiliza controles de janela, notificacoes nativas, sistema de arquivos
 * e captura de tela para aplicacoes desktop.
 * Em ambiente web (nao-Electron), todos os metodos retornam valores default
 * ou sao no-ops, permitindo que a aplicacao funcione sem modificacoes.
 *
 * @class ElectronService
 * @example
 * ```ts
 * const electron = inject(ElectronService);
 * if (electron.isElectron) {
 *   electron.minimizeWindow();
 * }
 * ```
 */
@Injectable({
  providedIn: 'root',
})
export class ElectronService {
  private get api(): ElectronAPI | undefined {
    return window.electronAPI;
  }

  /** Indica se a aplicacao esta rodando dentro do Electron. */
  get isElectron(): boolean {
    return !!this.api;
  }

  /** Minimiza a janela da aplicacao. */
  minimizeWindow(): void {
    this.api?.minimizeWindow();
  }

  /** Maximiza ou restaura a janela da aplicacao. */
  maximizeWindow(): void {
    this.api?.maximizeWindow();
  }

  /** Fecha a janela da aplicacao. */
  closeWindow(): void {
    this.api?.closeWindow();
  }

  /**
   * Verifica se a janela esta maximizada.
   *
   * @returns Promise com true se maximizada, false caso contrario
   */
  async isMaximized(): Promise<boolean> {
    return this.api?.isMaximized() ?? false;
  }

  /**
   * Obtem a versao atual da aplicacao.
   *
   * @returns Promise com string de versao (ex: '1.0.0')
   */
  async getAppVersion(): Promise<string> {
    return this.api?.getAppVersion() ?? '0.0.0';
  }

  /**
   * Retorna a plataforma onde a aplicacao esta rodando (ex: 'win32', 'darwin', 'linux').
   *
   * @returns String com o nome da plataforma
   */
  getPlatform(): string {
    return this.api?.getPlatform() ?? 'unknown';
  }

  /**
   * Abre uma URL no navegador padrao do sistema.
   *
   * @param url - URL completa a ser aberta
   * @returns Promise com true se sucesso, false caso contrario
   */
  async openExternal(url: string): Promise<boolean> {
    return this.api?.openExternal(url) ?? false;
  }

  /**
   * Exibe uma notificacao nativa do sistema operacional.
   *
   * @param title - Titulo da notificacao
   * @param body - Corpo/mensagem da notificacao
   */
  showNotification(title: string, body: string): void {
    this.api?.showNotification(title, body);
  }

  /**
   * Salva dados binarios em um arquivo no sistema de arquivos do usuario.
   *
   * @param data - Conteudo binario a ser salvo
   * @param filename - Nome do arquivo
   * @returns Promise com o caminho absoluto do arquivo salvo, ou null se cancelado
   */
  async saveFile(data: ArrayBuffer, filename: string): Promise<string | null> {
    return this.api?.saveFile(data, filename) ?? null;
  }

  /**
   * Abre uma caixa de dialogo para selecao de arquivo.
   *
   * @param options - Opcionalmente especifica filtros de extensoes
   * @returns Promise com path e conteudo binario do arquivo, ou null se cancelado
   */
  async openFile(options?: { filters?: { name: string; extensions: string[] }[] }): Promise<{
    path: string;
    content: ArrayBuffer;
  } | null> {
    return this.api?.openFile(options) ?? null;
  }

  /**
   * Abre o gerenciador de arquivos do sistema na pasta especificada.
   *
   * @param path - Caminho da pasta a abrir
   * @returns Promise com o caminho aberto (pode ser vazio em caso de falha)
   */
  async openFolder(path: string): Promise<string> {
    return this.api?.openFolder(path) ?? '';
  }

  /**
   * Retorna as fontes de tela disponiveis para captura (screenshare).
   *
   * @returns Promise com lista de ScreenSource (monitores e janelas)
   */
  async getScreenSources(): Promise<ScreenSource[]> {
    return this.api?.getScreenSources() ?? [];
  }

  /**
   * Verifica junto ao servidor de atualizacoes se ha uma nova versao disponivel.
   *
   * @returns Promise com informacoes da versao ou null se indisponivel
   */
  async checkForUpdates(): Promise<UpdateInfo | null> {
    return this.api?.checkForUpdates() ?? null;
  }

  /**
   * Baixa a atualizacao disponivel.
   *
   * @returns Promise com true se o download iniciou com sucesso
   */
  async downloadUpdate(): Promise<boolean> {
    return this.api?.downloadUpdate() ?? false;
  }

  /**
   * Instala a atualizacao baixada e reinicia a aplicacao.
   */
  installUpdate(): void {
    this.api?.installUpdate();
  }

  /**
   * Registra um callback para ser chamado quando o estado de maximizado mudar.
   *
   * @param callback - Funcao chamada com true se maximizada, false se restaurada
   */
  onMaximizedChange(callback: (isMaximized: boolean) => void): void {
    this.api?.onMaximizedChange(callback);
  }
}
