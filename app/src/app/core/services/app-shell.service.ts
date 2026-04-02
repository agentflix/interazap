import { Injectable, signal } from '@angular/core';

/**
 * Service for controlling app shell visibility and scroll behavior.
 *
 * @remarks
 * Manages footer visibility and content scrollability states,
 * typically used for modal dialogs and full-screen experiences.
 */
@Injectable({ providedIn: 'root' })
export class AppShellService {
  readonly footerVisible = signal(true);
  readonly contentScrollable = signal(true);

  /**
   * Shows the app shell footer.
   */
  showFooter(): void {
    this.footerVisible.set(true);
  }

  /**
   * Hides the app shell footer.
   */
  hideFooter(): void {
    this.footerVisible.set(false);
  }

  /**
   * Enables scrolling on the main content area.
   */
  enableContentScroll(): void {
    this.contentScrollable.set(true);
  }

  /**
   * Disables scrolling on the main content area.
   */
  disableContentScroll(): void {
    this.contentScrollable.set(false);
  }
}
