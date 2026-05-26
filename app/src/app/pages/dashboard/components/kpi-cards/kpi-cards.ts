import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { CurrencyPipe, DecimalPipe } from '@angular/common';
import { LucideAngularModule } from 'lucide-angular';
import { AfCardComponent } from '@shared/components';
import { type DashboardSummary } from '../../models/dashboard.model';

/**
 * Linha de cards KPI — exibe as quatro métricas principais do dashboard.
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
