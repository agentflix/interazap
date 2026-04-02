import { Injectable, inject, signal } from '@angular/core';
import { ElectronService } from './electron.service';

interface UpdateInfo {
  version: string;
  releaseDate: string;
}

/**
 * Servico para verificacao e instalacao de atualizacoes da aplicacao Electron.
 * Abstrai a API Electron e fornece signals reativos para UI.
 *
 * @class UpdateService
 * @example
 * ```ts
 * const update = inject(UpdateService);
 * await update.checkForUpdates();
 * if (update.available()) update.downloadUpdate();
 * ```
 */
@Injectable({
  providedIn: 'root',
})
export class UpdateService {
  private electronService = inject(ElectronService);

  /** Indica se ha uma atualizacao disponivel para download. */
  available = signal(false);

  /** Indica se o download da atualizacao esta em andamento. */
  downloading = signal(false);

  /** Informacoes da versao disponivel (versao e data de lancamento). */
  updateInfo = signal<UpdateInfo | null>(null);

  /**
   * Verifica se ha atualizacoes disponiveis no servidor de atualizacoes.
   * Fallback silencioso em ambiente web (nao-Electron).
   *
   * @returns Promise<void>
   */
  async checkForUpdates(): Promise<void> {
    if (!this.electronService.isElectron) {
      return;
    }

    try {
      const info = await this.electronService.checkForUpdates();
      if (info) {
        this.updateInfo.set({
          version: info.version,
          releaseDate: info.releaseDate,
        });
        this.available.set(true);
      }
    } catch {
      // Silent fail - updates are optional
    }
  }

  /**
   * Inicia o download da atualizacao disponivel.
   * Fallback silencioso em ambiente web.
   *
   * @returns Promise<void>
   */
  async downloadUpdate(): Promise<void> {
    if (!this.electronService.isElectron) {
      return;
    }

    this.downloading.set(true);
    try {
      await this.electronService.downloadUpdate();
    } catch {
      this.downloading.set(false);
    }
  }

  /**
   * Instala a atualizacao baixada e reinicia a aplicacao.
   * So tem efeito em ambiente Electron.
   */
  installUpdate(): void {
    if (!this.electronService.isElectron) {
      return;
    }
    this.electronService.installUpdate();
  }
}
