import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { AfAlertComponent, AfButtonComponent } from '@shared/components';

/** Maps English backend messages to Portuguese user-friendly messages. */
const MESSAGE_TRANSLATIONS: Record<string, string> = {
  'This action is unauthorized.': 'Você não tem permissão para realizar esta ação.',
  'Access denied.': 'Acesso negado.',
};

/**
 * Access Denied page — displayed when a user attempts an action
 * they do not have permission to perform (HTTP 403).
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

  goHome(): void {
    void this.router.navigate(['/']);
  }
}
