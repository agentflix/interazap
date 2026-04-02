import {
  type OnInit,
  ChangeDetectionStrategy,
  Component,
  computed,
  inject,
  signal,
} from '@angular/core';
import { DecimalPipe } from '@angular/common';
import { catchError, forkJoin, of } from 'rxjs';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfAlertComponent,
  AfButtonComponent,
  AfEmptyStateComponent,
  AfPageTitleComponent,
} from '@shared/components';
import { AiUsageService } from '@ai/services/ai-usage.service';
import {
  type UsageAgent,
  type UsageBudgetStatus,
  type UsageDaily,
  type UsageSummary,
  type UsageVoice,
} from '@ai/models/ai.model';

/**
 * Dashboard showing AI usage metrics and costs.
 * Business logic preserved verbatim from source. Visual layer migrated to UI Kit.
 */
@Component({
  selector: 'app-ai-usage-dashboard',
  standalone: true,
  imports: [
    DecimalPipe,
    LucideAngularModule,
    AfPageTitleComponent,
    AfButtonComponent,
    AfAlertComponent,
    AfEmptyStateComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './usage-dashboard.html',
})
export class UsageDashboardComponent implements OnInit {
  private readonly usageService = inject(AiUsageService);

  /** Expose Math to template for budget bar width calc. */
  readonly Math = Math;

  readonly isLoading = signal(true);
  readonly hasError = signal(false);

  readonly summary = signal<UsageSummary | null>(null);
  readonly dailyUsage = signal<UsageDaily[]>([]);
  readonly topAgents = signal<UsageAgent[]>([]);
  readonly budgetStatus = signal<UsageBudgetStatus | null>(null);
  readonly voiceUsage = signal<UsageVoice | null>(null);

  readonly trendIcon = computed(() =>
    (this.summary()?.cost_change_percent ?? 0) >= 0 ? 'trending-up' : 'trending-down',
  );

  readonly trendClass = computed(() =>
    (this.summary()?.cost_change_percent ?? 0) >= 0
      ? 'text-red-600 dark:text-red-400'
      : 'text-emerald-600 dark:text-emerald-400',
  );

  readonly chartData = computed(() => {
    const data = this.dailyUsage();
    if (data.length === 0) return [];

    // Normalize for chart display (max height 100px)
    const maxCost = Math.max(...data.map((d) => d.cost), 0.01);
    return data.map((d) => ({
      date: d.date,
      height: Math.max(4, (d.cost / maxCost) * 100),
      cost: d.cost,
      tokens: d.tokens,
    }));
  });

  ngOnInit(): void {
    this.loadData();
  }

  /**
   * Load all dashboard data.
   */
  private loadData(): void {
    this.isLoading.set(true);
    this.hasError.set(false);

    forkJoin({
      summary: this.usageService.getSummary(),
      daily: this.usageService.getDaily(30),
      topAgents: this.usageService.getTopAgents(5),
      budget: this.usageService.getBudgetStatus().pipe(catchError(() => of(null))),
      voice: this.usageService.getVoiceUsage(30).pipe(catchError(() => of(null))),
    }).subscribe({
      next: (result) => {
        this.summary.set(result.summary);
        this.dailyUsage.set(result.daily);
        this.topAgents.set(result.topAgents);
        this.budgetStatus.set(result.budget);
        this.voiceUsage.set(result.voice);
        this.isLoading.set(false);
      },
      error: () => {
        this.isLoading.set(false);
        this.hasError.set(true);
      },
    });
  }

  /**
   * Retry loading data.
   */
  retry(): void {
    this.loadData();
  }

  /**
   * Format large numbers.
   */
  formatNumber(num: number): string {
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
    return String(num);
  }

  /**
   * Format currency values as BRL.
   */
  formatCurrency(value: number): string {
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL',
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(value);
  }
}
