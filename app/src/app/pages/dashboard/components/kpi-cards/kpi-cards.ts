import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { CurrencyPipe, DecimalPipe } from '@angular/common';
import { LucideAngularModule } from 'lucide-angular';
import { AfCardComponent } from '@shared/components';
import { type DashboardSummary } from '../../models/dashboard.model';

/**
 * KPI cards row — displays the four main dashboard metrics.
 * Each card has a semantic icon color and descriptive subtitle.
 */
@Component({
  selector: 'app-kpi-cards',
  standalone: true,
  imports: [AfCardComponent, LucideAngularModule, CurrencyPipe, DecimalPipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './kpi-cards.html',
})
export class KpiCardsComponent {
  readonly summary = input.required<DashboardSummary>();
}

export default KpiCardsComponent;
