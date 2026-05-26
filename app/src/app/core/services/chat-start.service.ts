import { Injectable, signal } from '@angular/core';

/**
 * Sinaliza a intenção de abrir o diálogo de início de novo chat.
 *
 * @remarks
 * Usado como signal leve para coordenar a abertura do modal de início de chat
 * a partir de diferentes partes da aplicação.
 */
@Injectable({ providedIn: 'root' })
export class ChatStartService {
  private readonly openRequest = signal(false);

  readonly requestOpen = this.openRequest.asReadonly();

  /**
   * Sinaliza que o diálogo de início de chat deve ser aberto.
   */
  open(): void {
    this.openRequest.set(true);
  }

  /**
   * Limpa o signal de abertura (ex.: após o diálogo ser fechado).
   */
  clear(): void {
    this.openRequest.set(false);
  }
}
