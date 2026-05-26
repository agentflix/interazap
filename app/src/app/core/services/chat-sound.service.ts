import { Injectable, computed, signal } from '@angular/core';
import { isChatMuted, setChatMuted } from 'src/app/shared/utils/notifications/chat-audio';

/**
 * Gerencia o estado de mudo das notificações sonoras do chat.
 *
 * @remarks
 * Persiste o estado de mudo no localStorage via funções utilitárias
 * e o expõe como um signal reativo.
 */
@Injectable({ providedIn: 'root' })
export class ChatSoundService {
  private readonly muted = signal(isChatMuted());

  readonly mutedState = computed(() => this.muted());

  /**
   * Alterna o estado de mudo do som.
   */
  toggle(): void {
    this.setMuted(!this.muted());
  }

  /**
   * Define o estado de mudo do som.
   *
   * @param value - `true` para mutar, `false` para desmutar
   */
  setMuted(value: boolean): void {
    this.muted.set(value);
    setChatMuted(value);
  }
}
