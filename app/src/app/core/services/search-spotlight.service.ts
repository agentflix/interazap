import { Injectable, signal } from '@angular/core';

/**
 * Global trigger service to open/close spotlight from layout components.
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
