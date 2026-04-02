import { Component, ChangeDetectionStrategy, inject } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { SidenavComponent } from '../components/sidenav/sidenav';
import { TopbarComponent } from '../components/topbar/topbar';
import { FooterComponent } from '../components/footer/footer';
import { AppShellService } from '../../core/services/app-shell.service';
import { SearchSpotlightComponent } from '../../shared/components/search-spotlight/search-spotlight';

/**
 * Main application shell layout.
 * Orchestrates the sidebar, topbar, content area, and footer.
 * All authenticated pages render inside the `<router-outlet>`.
 *
 * Uses `AppShellService` to allow child pages (e.g. Chat) to hide
 * the footer and disable the content scroll for full-height layouts.
 *
 * @example
 * ```ts
 * // In app.routes.ts:
 * { path: '', component: MainLayoutComponent, children: [...] }
 * ```
 */
@Component({
  selector: 'af-main-layout',
  standalone: true,
  imports: [
    RouterOutlet,
    SidenavComponent,
    TopbarComponent,
    FooterComponent,
    SearchSpotlightComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './main-layout.html',
})
export class MainLayoutComponent {
  readonly appShell = inject(AppShellService);
}
