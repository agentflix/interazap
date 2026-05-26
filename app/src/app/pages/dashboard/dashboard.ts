import {
  type OnInit,
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  inject,
  signal,
} from '@angular/core';
import { forkJoin } from 'rxjs';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfPageTitleComponent,
  AfButtonComponent,
  AfAlertComponent,
  AfEmptyStateComponent,
  AfSkeletonComponent,
  AfCardComponent,
} from '@shared/components';
import { DashboardService } from './services/dashboard.service';
import { type DashboardData, type DateRange } from './models/dashboard.model';
import { DashboardDateFilterComponent } from './components/date-filter/date-filter';
import { KpiCardsComponent } from './components/kpi-cards/kpi-cards';
import { RevenueChartComponent } from './components/revenue-chart/revenue-chart';
import { NegotiationStatsComponent } from './components/negotiation-stats/negotiation-stats';
import { TicketStatsComponent } from './components/ticket-stats/ticket-stats';
import { CsatChartComponent } from './components/csat-chart/csat-chart';
import { SalesFunnelChartComponent } from './components/sales-funnel-chart/sales-funnel-chart';
import { RecentActivitiesComponent } from './components/recent-activities/recent-activities';

/**
 * Página do dashboard — ponto de entrada principal com métricas KPI, gráficos e atividades recentes.
 */
@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [
    LucideAngularModule,
    AfPageTitleComponent,
    AfButtonComponent,
    AfAlertComponent,
    AfEmptyStateComponent,
    AfSkeletonComponent,
    AfCardComponent,
    DashboardDateFilterComponent,
    KpiCardsComponent,
    RevenueChartComponent,
    NegotiationStatsComponent,
    TicketStatsComponent,
    CsatChartComponent,
    SalesFunnelChartComponent,
    RecentActivitiesComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './dashboard.html',
})
export class DashboardComponent implements OnInit {
  private readonly dashboardService = inject(DashboardService);
  private readonly destroyRef = inject(DestroyRef);

  readonly isLoading = signal(true);
  readonly hasError = signal(false);
  readonly data = signal<DashboardData | null>(null);
  readonly currentRange = signal<DateRange | null>(null);

  ngOnInit(): void {
    this.loadData();
  }

  retry(): void {
    this.loadData();
  }

  onFilterChanged(range: DateRange): void {
    this.currentRange.set(range);
    this.loadData();
  }

  private loadData(): void {
    this.isLoading.set(true);
    this.hasError.set(false);

    const range = this.currentRange();
    const dateFrom = range?.from;
    const dateTo = range?.to;
    const period = range ? undefined : 30;

    forkJoin({
      dashboard: this.dashboardService.getData(dateFrom, dateTo, period),
    })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (result) => {
          this.data.set(result.dashboard.data);
          this.isLoading.set(false);
        },
        error: () => {
          this.isLoading.set(false);
          this.hasError.set(true);
        },
      });
  }
}

export default DashboardComponent;
