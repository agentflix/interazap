import {
  type OnInit,
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  inject,
  signal,
} from '@angular/core';
import { RouterLink } from '@angular/router';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { DecimalPipe } from '@angular/common';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfBadgeComponent,
  AfCardComponent,
  AfDataTableComponent,
  AfEmptyStateComponent,
  AfIconButtonComponent,
  AfPageTitleComponent,
  AfStatusBadgeComponent,
} from '@shared/components';
import { type QueueMetrics, QueueService } from '@core/services/queue.service';

/**
 * Página de painel de monitoramento das filas BullMQ no módulo Admin.
 * Exibe métricas em tempo real e permite pausar, retomar e limpar filas individualmente.
 */
@Component({
  selector: 'app-queue-dashboard',
  standalone: true,
  imports: [
    RouterLink,
    DecimalPipe,
    LucideAngularModule,
    AfPageTitleComponent,
    AfBadgeComponent,
    AfIconButtonComponent,
    AfCardComponent,
    AfDataTableComponent,
    AfStatusBadgeComponent,
    AfEmptyStateComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './queue-dashboard.html',
})
export class QueueDashboardComponent implements OnInit {
  private readonly queueService = inject(QueueService);
  private readonly destroyRef = inject(DestroyRef);

  readonly overview = this.queueService.overview;
  readonly loading = signal(false);
  readonly actionLoading = signal<string | null>(null);
  readonly isConnected = this.queueService.isConnected;

  readonly queues = computed<QueueMetrics[]>(() => this.overview()?.queues ?? []);

  ngOnInit(): void {
    this.startPolling();
    this.subscribeToUpdates();
  }

  /** Recarrega manualmente as métricas do painel de filas. */
  refresh(): void {
    this.loading.set(true);
    this.queueService
      .getOverview()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => this.loading.set(false),
        error: () => this.loading.set(false),
      });
  }

  /**
   * Pausa o processamento de uma fila específica.
   * @param queueName Nome da fila a pausar
   */
  pauseQueue(queueName: string): void {
    this.actionLoading.set(queueName);
    this.queueService
      .pauseQueue(queueName)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => this.actionLoading.set(null),
        error: () => this.actionLoading.set(null),
      });
  }

  /**
   * Retoma o processamento de uma fila pausada.
   * @param queueName Nome da fila a retomar
   */
  resumeQueue(queueName: string): void {
    this.actionLoading.set(queueName);
    this.queueService
      .resumeQueue(queueName)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => this.actionLoading.set(null),
        error: () => this.actionLoading.set(null),
      });
  }

  /**
   * Remove jobs com determinado status de uma fila.
   * @param queueName Nome da fila a limpar
   * @param status Status dos jobs a remover
   */
  cleanQueue(queueName: string, status: 'completed' | 'failed' | 'delayed' | 'wait'): void {
    this.actionLoading.set(queueName);
    this.queueService
      .cleanQueue(queueName, status)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => this.actionLoading.set(null),
        error: () => this.actionLoading.set(null),
      });
  }

  private startPolling(): void {
    this.loading.set(true);
    this.queueService
      .startPolling(10000)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => this.loading.set(false),
        error: () => this.loading.set(false),
      });
  }

  private subscribeToUpdates(): void {
    this.queueService
      .subscribeToQueueEvents()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => {
        this.queueService.refresh();
      });
  }
}

export default QueueDashboardComponent;
