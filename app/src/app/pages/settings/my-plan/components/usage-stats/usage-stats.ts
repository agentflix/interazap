import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { type SubscriptionUsage } from '@shared/models/subscription.model';

/**
 * Bloco de métricas de uso atual do tenant.
 */
@Component({
  selector: 'app-usage-stats',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './usage-stats.html',
})
export class UsageStatsComponent {
  readonly usage = input.required<SubscriptionUsage>();
}
