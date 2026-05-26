import { ChangeDetectionStrategy, Component, computed, input, output } from '@angular/core';
import { RouterLink } from '@angular/router';
import { type Negotiation } from 'src/app/core/services/negotiation.service';
import { ButtonComponent, IconButtonComponent } from '@shared/components/buttons';
import { type MetricCard, type NegotiationBadge } from '../../negotiation-show.model';
import { badgeClass } from '../../negotiation-show.utils';

/**
 * Cabeçalho presentacional da página de detalhes da negociação.
 */
@Component({
  selector: 'app-negotiation-header',
  standalone: true,
  imports: [RouterLink, ButtonComponent, IconButtonComponent],
  templateUrl: './negotiation-header.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: { class: 'block' },
})
export class NegotiationHeaderComponent {
  readonly negotiation = input<Negotiation | null>(null);
  readonly isUpdatingStatus = input(false);
  readonly badges = input<NegotiationBadge[]>([]);
  readonly metrics = input<MetricCard[]>([]);

  readonly markAsWon = output<void>();
  readonly markAsLost = output<void>();
  readonly reopen = output<void>();

  readonly canReopen = computed(() => this.negotiation()?.status !== 'open');
  readonly showReopen = computed(() => {
    const status = this.negotiation()?.status;
    return status === 'won' || status === 'lost';
  });

  badgeClass(badge: NegotiationBadge): string {
    return badgeClass(badge);
  }

  formatDateTime(value?: string | null): string {
    if (!value) return '-';
    const date = new Date(value);
    return Number.isNaN(date.getTime())
      ? '-'
      : date.toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
  }
}
