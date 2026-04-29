import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  inject,
  input,
  signal,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { interval } from 'rxjs';
import { LucideAngularModule } from 'lucide-angular';

/**
 * Janela de 24h da Meta para mensagens free-form.
 *
 * Estados:
 * - safe (>12h restantes): verde
 * - warning (4–12h): amarelo
 * - danger (<4h): vermelho
 * - expired: cinza
 */
@Component({
  selector: 'app-window-24h-badge',
  standalone: true,
  imports: [LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <span
      class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-full"
      [class]="badgeClass()"
      role="status"
      [attr.aria-label]="ariaLabel()"
    >
      <lucide-icon name="clock" [size]="12" />
      {{ label() }}
    </span>
  `,
})
export class Window24hBadgeComponent {
  private readonly destroyRef = inject(DestroyRef);

  /** Última mensagem inbound (ISO string ou null). */
  readonly lastInboundAt = input<string | null>(null);

  private readonly now = signal(Date.now());

  constructor() {
    interval(60_000)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.now.set(Date.now()));
  }

  /** Tempo restante em minutos (negativo = expirado). */
  readonly remainingMinutes = computed<number>(() => {
    const last = this.lastInboundAt();
    if (!last) return -1;
    const lastMs = new Date(last).getTime();
    if (Number.isNaN(lastMs)) return -1;
    const expiresAt = lastMs + 24 * 60 * 60 * 1000;
    return Math.floor((expiresAt - this.now()) / 60_000);
  });

  readonly status = computed<'safe' | 'warning' | 'danger' | 'expired'>(() => {
    const m = this.remainingMinutes();
    if (m <= 0) return 'expired';
    if (m < 4 * 60) return 'danger';
    if (m < 12 * 60) return 'warning';
    return 'safe';
  });

  readonly label = computed<string>(() => {
    const m = this.remainingMinutes();
    if (m <= 0) return 'Janela expirada';
    const hours = Math.floor(m / 60);
    const minutes = m % 60;
    if (hours > 0) return `${hours}h${minutes.toString().padStart(2, '0')} restantes`;
    return `${minutes}min restantes`;
  });

  readonly ariaLabel = computed<string>(() => `Janela 24h Meta — ${this.label()}`);

  readonly badgeClass = computed<string>(() => {
    switch (this.status()) {
      case 'safe':
        return 'bg-success-100 text-success-700 dark:bg-success-900/40 dark:text-success-300';
      case 'warning':
        return 'bg-warning-100 text-warning-700 dark:bg-warning-900/40 dark:text-warning-300';
      case 'danger':
        return 'bg-danger-100 text-danger-700 dark:bg-danger-900/40 dark:text-danger-300';
      case 'expired':
      default:
        return 'bg-neutral-200 text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300';
    }
  });
}
