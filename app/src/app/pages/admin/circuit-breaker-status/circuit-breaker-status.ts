import {
  type OnInit,
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  inject,
  signal,
} from '@angular/core';
import { DatePipe } from '@angular/common';
import { RouterLink } from '@angular/router';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfBadgeComponent,
  AfButtonComponent,
  AfCardComponent,
  AfEmptyStateComponent,
  AfIconButtonComponent,
  AfPageTitleComponent,
} from '@shared/components';
import {
  type CircuitBreakerOverview,
  type CircuitBreakerStatus,
  type CircuitState,
  type CircuitStateChange,
  QueueService,
} from '@core/services/queue.service';

const STATE_LABELS: Record<CircuitState, string> = {
  CLOSED: 'Fechado',
  HALF_OPEN: 'Semi-Aberto',
  OPEN: 'Aberto',
};

@Component({
  selector: 'app-circuit-breaker-status',
  standalone: true,
  imports: [
    DatePipe,
    RouterLink,
    LucideAngularModule,
    AfPageTitleComponent,
    AfIconButtonComponent,
    AfCardComponent,
    AfBadgeComponent,
    AfButtonComponent,
    AfEmptyStateComponent,
/**
 * Circuit breaker status page component for the Admin module.
 * @selector app-circuit-breaker-status
 */
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './circuit-breaker-status.html',
})
export class CircuitBreakerStatusComponent implements OnInit {
  private readonly queueService = inject(QueueService);
  private readonly destroyRef = inject(DestroyRef);

  readonly loading = signal(false);
  readonly actionLoading = signal<string | null>(null);
  readonly circuitData = signal<CircuitBreakerOverview | null>(null);
  readonly expandedCircuits = signal(new Set<string>());

  readonly circuits = computed<CircuitBreakerStatus[]>(() => this.circuitData()?.circuits ?? []);

  ngOnInit(): void {
    this.loadCircuits();
    this.subscribeToUpdates();
  }

  refresh(): void {
    this.loadCircuits();
  }

  getStateLabel(state: CircuitState): string {
    return STATE_LABELS[state];
  }

  stateVariant(state: CircuitState): 'success' | 'warning' | 'danger' {
    if (state === 'CLOSED') return 'success';
    if (state === 'HALF_OPEN') return 'warning';
    return 'danger';
  }

  stateIconWrapper(state: CircuitState): string {
    if (state === 'CLOSED') return 'bg-success/15';
    if (state === 'HALF_OPEN') return 'bg-warning/15';
    return 'bg-danger/15';
  }

  resetCircuit(name: string): void {
    this.actionLoading.set(name);
    this.queueService
      .resetCircuitBreaker(name)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.actionLoading.set(null);
          this.loadCircuits();
        },
        error: () => this.actionLoading.set(null),
      });
  }

  openCircuit(name: string): void {
    this.actionLoading.set(name);
    this.queueService
      .openCircuitBreaker(name)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.actionLoading.set(null);
          this.loadCircuits();
        },
        error: () => this.actionLoading.set(null),
      });
  }

  toggleHistory(name: string): void {
    this.expandedCircuits.update((set) => {
      const newSet = new Set(set);
      if (newSet.has(name)) {
        newSet.delete(name);
      } else {
        newSet.add(name);
      }
      return newSet;
    });
  }

  private loadCircuits(): void {
    this.loading.set(true);
    this.queueService
      .getCircuitBreakers()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (data) => {
          this.circuitData.set(data);
          this.loading.set(false);
        },
        error: () => this.loading.set(false),
      });
  }

  private subscribeToUpdates(): void {
    this.queueService
      .subscribeToCircuitEvents()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((event: CircuitStateChange & { name: string }) => {
        this.circuitData.update((data) => {
          if (!data) return data;

          const circuits = data.circuits.map((c) => {
            if (c.name === event.name) {
              return {
                ...c,
                state: event.to,
                history: [
                  {
                    from: event.from,
                    to: event.to,
                    timestamp: event.timestamp,
                    reason: event.reason,
                  },
                  ...c.history,
                ],
              };
            }
            return c;
          });

          return {
            ...data,
            circuits,
            totalClosed: circuits.filter((c) => c.state === 'CLOSED').length,
            totalHalfOpen: circuits.filter((c) => c.state === 'HALF_OPEN').length,
            totalOpen: circuits.filter((c) => c.state === 'OPEN').length,
          };
        });
      });
  }
}

export default CircuitBreakerStatusComponent;
