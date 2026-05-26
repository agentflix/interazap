import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { AfAlertComponent, AfButtonComponent } from '@shared/components';

/** Mapeia mensagens do backend em inglês para mensagens amigáveis em português. */
const MESSAGE_TRANSLATIONS: Record<string, string> = {
  'This action is unauthorized.': 'Você não tem permissão para realizar esta ação.',
  'Access denied.': 'Acesso negado.',
};

/**
 * Página de acesso negado — exibida quando o usuário tenta uma ação
 * para a qual não possui permissão (HTTP 403).
 */
@Component({
  selector: 'app-access-denied',
  standalone: true,
  imports: [AfAlertComponent, AfButtonComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './access-denied.html',
})
export class AccessDeniedComponent {
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);

  private readonly rawMessage = signal('Você não tem permissão para realizar esta ação.');

  readonly displayMessage = computed(() => {
    const raw = this.rawMessage();
    return MESSAGE_TRANSLATIONS[raw] ?? raw;
  });

  constructor() {
    const message = this.route.snapshot.queryParamMap.get('message');
    if (message) {
      this.rawMessage.set(decodeURIComponent(message));
    }
  }

  /** Navega para a página inicial da aplicação. */
  goHome(): void {
    void this.router.navigate(['/']);
  }
}
