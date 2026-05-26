import { Injectable, signal } from '@angular/core';

/**
 * Serviço para abrir/fechar o spotlight de busca global a partir de componentes de layout.
 *
 * Contexto: signal-based trigger compartilhado entre layout e componente spotlight.
 */
@Injectable({ providedIn: 'root' })
export class SearchSpotlightService {
  private readonly openSignal = signal(false);

  readonly requestOpen = this.openSignal.asReadonly();

  open(): void {
    this.openSignal.set(true);
  }

  clear(): void {
    this.openSignal.set(false);
  }
}
