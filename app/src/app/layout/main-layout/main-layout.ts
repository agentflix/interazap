import { Component, ChangeDetectionStrategy, inject } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { SidenavComponent } from '../components/sidenav/sidenav';
import { TopbarComponent } from '../components/topbar/topbar';
import { FooterComponent } from '../components/footer/footer';
import { ImpersonationBannerComponent } from '../components/impersonation-banner/impersonation-banner';
import { AppShellService } from '../../core/services/app-shell.service';
import { SearchSpotlightComponent } from '../../shared/components/search-spotlight/search-spotlight';
import { TrialBannerComponent } from '../../core/components/trial-banner/trial-banner';

/**
 * Shell principal da aplicação autenticada.
 * Orquestra sidebar, topbar, área de conteúdo e rodapé.
 * Todas as páginas autenticadas são renderizadas dentro do `<router-outlet>`.
 *
 * Usa `AppShellService` para permitir que páginas filhas (ex: Chat) ocultem
 * o rodapé e desabilitem o scroll do conteúdo para layouts de altura total.
 *
 * @example
 * ```ts
 * // Em app.routes.ts:
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
    ImpersonationBannerComponent,
    SearchSpotlightComponent,
    TrialBannerComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './main-layout.html',
})
export class MainLayoutComponent {
  readonly appShell = inject(AppShellService);
}
