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
import { NgIcon, provideIcons } from '@ng-icons/core';
import { lucideClock3 } from '@ng-icons/lucide';

/**
 * Badge de janela de atendimento Meta (24h padrão / 72h CTWA) exibido acima do composer.
 *
 * @remarks
 * `expiresAt` é a fonte autoritativa da janela, calculada e persistida pelo backend
 * (`meta_window_expires_at` em `chat_tickets`). Quando `expiresAt` é `null` ou já está
 * no passado, o componente cai no cálculo de fallback `lastInboundAt + 24h` — defesa em
 * profundidade para nunca confiar cegamente num timestamp que pode estar desatualizado
 * (princípio 3 da skill `meta-whatsapp-expert`). O fallback sempre assume janela de 24h,
 * espelhando o `Branch 2` de `VerifyContactWindowAction` no backend.
 *
 * Estados de tom (independem do tipo de janela, medidos sobre o tempo restante absoluto):
 * - `safe` — tempo restante ≥ 4h
 * - `warning` — entre 1h e 4h
 * - `danger` — menos de 1h
 * - `expired` — ≤ 0 ou nenhuma fonte de janela válida
 *
 * Ver spec de design: `.context/DESIGN/meta-window-badge.md`.
 */
@Component({
  selector: 'app-meta-window-badge',
  standalone: true,
  imports: [NgIcon],
  changeDetection: ChangeDetectionStrategy.OnPush,
  viewProviders: [provideIcons({ lucideClock3 })],
  template: `
    <span
      class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-full"
      [class]="badgeClass()"
      role="status"
      [attr.aria-label]="ariaLabel()"
    >
      <ng-icon name="lucideClock3" size="12" />
      {{ label() }}
    </span>
  `,
})
export class MetaWindowBadgeComponent {
  private readonly destroyRef = inject(DestroyRef);

  /** Expiração autoritativa da janela (ISO string), calculada e persistida pelo backend. */
  readonly expiresAt = input<string | null>(null);

  /** Tipo de janela vindo do backend: `'24h'` padrão ou `'72h'` (CTWA). */
  readonly windowType = input<'24h' | '72h' | null>(null);

  /** Última mensagem inbound (ISO string ou null) — fallback quando `expiresAt` é ausente/passado. */
  readonly lastInboundAt = input<string | null>(null);

  private readonly now = signal(Date.now());

  constructor() {
    interval(60_000)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.now.set(Date.now()));
  }

  /** Expiração autoritativa em ms — só considerada válida quando presente e ainda no futuro. */
  private readonly authoritativeExpiresAtMs = computed<number | null>(() => {
    const raw = this.expiresAt();
    if (!raw) return null;
    const ms = new Date(raw).getTime();
    if (Number.isNaN(ms) || ms <= this.now()) return null;
    return ms;
  });

  /** Expiração calculada por fallback (`lastInboundAt + 24h`). */
  private readonly fallbackExpiresAtMs = computed<number | null>(() => {
    const last = this.lastInboundAt();
    if (!last) return null;
    const lastMs = new Date(last).getTime();
    if (Number.isNaN(lastMs)) return null;
    return lastMs + 24 * 60 * 60 * 1000;
  });

  /** True quando a fonte autoritativa (`expiresAt`) está sendo usada — não caiu no fallback. */
  readonly usingAuthoritativeSource = computed<boolean>(
    () => this.authoritativeExpiresAtMs() !== null,
  );

  /** Tipo efetivo exibido — o fallback sempre assume 24h, igual ao backend. */
  readonly effectiveType = computed<'24h' | '72h'>(() =>
    this.usingAuthoritativeSource() && this.windowType() === '72h' ? '72h' : '24h',
  );

  /** Tempo restante em minutos (≤ 0 = expirado/sem fonte válida). */
  readonly remainingMinutes = computed<number>(() => {
    const expiresAtMs = this.authoritativeExpiresAtMs() ?? this.fallbackExpiresAtMs();
    if (expiresAtMs === null) return -1;
    return Math.floor((expiresAtMs - this.now()) / 60_000);
  });

  readonly status = computed<'safe' | 'warning' | 'danger' | 'expired'>(() => {
    const m = this.remainingMinutes();
    if (m <= 0) return 'expired';
    if (m < 60) return 'danger';
    if (m < 4 * 60) return 'warning';
    return 'safe';
  });

  readonly label = computed<string>(() => {
    const m = this.remainingMinutes();
    if (m <= 0) return 'Janela expirada';
    const typeLabel = this.effectiveType() === '72h' ? '72h CTWA' : '24h';
    return `${typeLabel} · ${this.formatRemaining(m)}`;
  });

  readonly ariaLabel = computed<string>(() => {
    const m = this.remainingMinutes();
    if (m <= 0) {
      return 'Janela Meta expirada — apenas templates aprovados podem ser enviados';
    }

    const hours = Math.floor(m / 60);
    const minutes = m % 60;
    const tempoExtenso =
      hours > 0
        ? `${hours} hora${hours > 1 ? 's' : ''} e ${minutes} minuto${minutes !== 1 ? 's' : ''} restantes`
        : `${minutes} minuto${minutes !== 1 ? 's' : ''} restantes`;

    if (this.status() === 'danger') {
      return `Janela Meta ${tempoExtenso} — envie logo, a janela está prestes a expirar`;
    }

    const typeExtenso = this.effectiveType() === '72h' ? '72 horas CTWA' : '24 horas';
    return `Janela Meta ${typeExtenso} — ${tempoExtenso}`;
  });

  /**
   * Classes Tailwind por tom — reaproveitadas de `af-badge`
   * (`app/src/app/shared/components/badge/badge.ts`, variantes `success`/`warning`/`danger`/`default`),
   * apoiadas nos tokens reais de `app/src/styles.css`. Nenhum shade novo introduzido.
   */
  readonly badgeClass = computed<string>(() => {
    switch (this.status()) {
      case 'safe':
        return 'bg-primary-50 text-primary-700 dark:bg-primary-900 dark:text-primary-300';
      case 'warning':
        return 'bg-warning-50 text-warning-600 dark:bg-[#191d1a] dark:text-warning-500';
      case 'danger':
        return 'bg-danger-50 text-danger-600 dark:bg-[#191d1a] dark:text-danger-500';
      case 'expired':
      default:
        return 'bg-neutral-100 text-neutral-600 dark:bg-[#191d1a] dark:text-neutral-300';
    }
  });

  /** Formata minutos restantes: `23h 47m` (≥ 1h) ou `18m` (< 1h). */
  private formatRemaining(totalMinutes: number): string {
    if (totalMinutes < 60) return `${totalMinutes}m`;
    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;
    return `${hours}h ${minutes.toString().padStart(2, '0')}m`;
  }
}
