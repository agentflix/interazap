import { Injectable, signal } from '@angular/core';

/**
 * Service for signaling intent to open a new chat start dialog.
 *
 * @remarks
 * Used as a lightweight signal to coordinate opening the chat start
 * modal/dialog from different parts of the application.
 */
@Injectable({ providedIn: 'root' })
export class ChatStartService {
  private readonly openRequest = signal(false);

  readonly requestOpen = this.openRequest.asReadonly();

  /**
   * Signals that a chat start dialog should open.
   */
  open(): void {
    this.openRequest.set(true);
  }

  /**
   * Clears the open request signal (e.g., after dialog closes).
   */
  clear(): void {
    this.openRequest.set(false);
  }
}
