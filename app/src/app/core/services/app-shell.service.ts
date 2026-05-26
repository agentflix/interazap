import { Injectable, signal } from '@angular/core';

/**
 * Controla a visibilidade do rodapé e o comportamento de scroll do app shell.
 *
 * Gerencia o estado de visibilidade do footer e a capacidade de scroll do conteúdo,
 * usualmente acionado por diálogos modais ou experiências em tela cheia.
 */
@Injectable({ providedIn: 'root' })
export class AppShellService {
  readonly footerVisible = signal(true);
  readonly contentScrollable = signal(true);

  /** Exibe o rodapé do app shell. */
  showFooter(): void {
    this.footerVisible.set(true);
  }

  /** Oculta o rodapé do app shell. */
  hideFooter(): void {
    this.footerVisible.set(false);
  }

  /** Habilita o scroll na área principal de conteúdo. */
  enableContentScroll(): void {
    this.contentScrollable.set(true);
  }

  /** Desabilita o scroll na área principal de conteúdo. */
  disableContentScroll(): void {
    this.contentScrollable.set(false);
  }
}
