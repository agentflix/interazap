import { Injectable, computed, signal } from '@angular/core';
import { isChatMuted, setChatMuted } from 'src/app/shared/utils/notifications/chat-audio';

/**
 * Service for managing chat notification sound mute state.
 *
 * @remarks
 * Persists the muted state to localStorage via utility functions
 * and exposes it as a reactive signal.
 */
@Injectable({ providedIn: 'root' })
export class ChatSoundService {
  private readonly muted = signal(isChatMuted());

  readonly mutedState = computed(() => this.muted());

  /**
   * Toggles the sound mute state.
   */
  toggle(): void {
    this.setMuted(!this.muted());
  }

  /**
   * Sets the sound mute state.
   *
   * @param value - True to mute, false to unmute
   */
  setMuted(value: boolean): void {
    this.muted.set(value);
    setChatMuted(value);
  }
}
