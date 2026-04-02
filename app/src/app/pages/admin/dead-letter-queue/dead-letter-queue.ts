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
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { DatePipe, JsonPipe } from '@angular/common';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfBadgeComponent,
  AfButtonComponent,
  AfCardComponent,
  AfDataTableComponent,
  AfEmptyStateComponent,
  AfIconButtonComponent,
  AfModalComponent,
  AfPageTitleComponent,
  AfPaginationComponent,
  AfSelectInputComponent,
  AfTextInputComponent,
  type AfSelectOption,
} from '@shared/components';
import {
  type DeadLetterFilters,
  type DeadLetterJob,
  type DeadLetterQueueResponse,
  QueueService,
} from '@core/services/queue.service';

@Component({
  selector: 'app-dead-letter-queue',
  standalone: true,
  imports: [
    RouterLink,
    ReactiveFormsModule,
    DatePipe,
    JsonPipe,
    LucideAngularModule,
    AfPageTitleComponent,
    AfButtonComponent,
    AfCardComponent,
    AfSelectInputComponent,
    AfTextInputComponent,
    AfDataTableComponent,
    AfBadgeComponent,
    AfIconButtonComponent,
    AfPaginationComponent,
    AfEmptyStateComponent,
    AfModalComponent,
/**
 * Dead letter queue page component for the Admin module.
 * @selector app-dead-letter-queue
 */
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './dead-letter-queue.html',
})
export class DeadLetterQueueComponent implements OnInit {
  private readonly queueService = inject(QueueService);
  private readonly destroyRef = inject(DestroyRef);

  readonly loading = signal(false);
  readonly actionLoading = signal<string | null>(null);
  readonly jobs = signal<DeadLetterJob[]>([]);
  readonly filters = signal<DeadLetterFilters>({ page: 1, per_page: 10 });
  readonly pagination = signal<{
    total: number;
    page: number;
    per_page: number;
    totalPages: number;
  } | null>(null);
  readonly selectedJob = signal<DeadLetterJob | null>(null);

  readonly availableQueues = signal<string[]>([]);

  readonly queueOptions = computed<AfSelectOption[]>(() => [
    { label: 'Todas as filas', value: '' },
    ...this.availableQueues().map((queue) => ({ label: queue, value: queue })),
  ]);

  readonly queueControl = new FormControl('', { nonNullable: true });
  readonly jobNameControl = new FormControl('', { nonNullable: true });
  readonly fromDateControl = new FormControl('', { nonNullable: true });
  readonly toDateControl = new FormControl('', { nonNullable: true });

  readonly uniqueQueues = computed(() => {
    const queues = new Set(this.jobs().map((j) => j.queue));
    return Array.from(queues);
  });

  readonly lastFailedAt = computed(() => {
    const jobs = this.jobs();
    if (jobs.length === 0) return null;
    return jobs.reduce((latest, job) => {
      const jobDate = new Date(job.failedAt);
      return jobDate > latest ? jobDate : latest;
    }, new Date(0));
  });

  readonly hasActiveFilters = computed(() => {
    const f = this.filters();
    return !!(f.queue || f.jobName || f.from_date || f.to_date || f.tenant_id);
  });

  ngOnInit(): void {
    this.loadJobs();
    this.loadAvailableQueues();
    this.setupFilterControls();
    this.subscribeToNewJobs();
  }

  refresh(): void {
    this.loadJobs();
  }

  updateFilter(key: keyof DeadLetterFilters, value: string): void {
    this.filters.update((f) => ({
      ...f,
      [key]: value || undefined,
      page: 1,
    }));
    this.loadJobs();
  }

  clearFilters(): void {
    this.filters.set({ page: 1, per_page: 10 });
    this.queueControl.setValue('', { emitEvent: false });
    this.jobNameControl.setValue('', { emitEvent: false });
    this.fromDateControl.setValue('', { emitEvent: false });
    this.toDateControl.setValue('', { emitEvent: false });
    this.loadJobs();
  }

  goToPage(page: number): void {
    this.filters.update((f) => ({ ...f, page }));
    this.loadJobs();
  }

  retryJob(jobId: string): void {
    this.actionLoading.set(jobId);
    this.queueService
      .retryDeadLetterJob(jobId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.actionLoading.set(null);
          this.loadJobs();
        },
        error: () => this.actionLoading.set(null),
      });
  }

  retryAll(): void {
    this.loading.set(true);
    this.queueService
      .retryAllDeadLetterJobs(this.filters())
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.loading.set(false);
          this.loadJobs();
        },
        error: () => this.loading.set(false),
      });
  }

  purgeJob(jobId: string): void {
    this.actionLoading.set(jobId);
    this.queueService
      .purgeDeadLetterJob(jobId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.actionLoading.set(null);
          this.loadJobs();
        },
        error: () => this.actionLoading.set(null),
      });
  }

  purgeAll(): void {
    this.loading.set(true);
    this.queueService
      .purgeAllDeadLetterJobs(this.filters())
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.loading.set(false);
          this.loadJobs();
        },
        error: () => this.loading.set(false),
      });
  }

  showDetails(job: DeadLetterJob): void {
    this.selectedJob.set(job);
  }

  showStacktrace(job: DeadLetterJob): void {
    this.selectedJob.set(job);
  }

  closeModal(): void {
    this.selectedJob.set(null);
  }

  private loadJobs(): void {
    this.loading.set(true);
    this.queueService
      .listDeadLetterJobs(this.filters())
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response: DeadLetterQueueResponse) => {
          this.jobs.set(response.jobs);
          this.pagination.set({
            total: response.total,
            page: response.page,
            per_page: response.per_page,
            totalPages: response.totalPages,
          });
          this.loading.set(false);
        },
        error: () => this.loading.set(false),
      });
  }

  private setupFilterControls(): void {
    this.queueControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((value) => this.updateFilter('queue', value));

    this.jobNameControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((value) => this.updateFilter('jobName', value));

    this.fromDateControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((value) => this.updateFilter('from_date', value));

    this.toDateControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((value) => this.updateFilter('to_date', value));
  }

  private loadAvailableQueues(): void {
    this.queueService
      .getOverview()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((overview) => {
        this.availableQueues.set(overview.queues.map((q) => q.name));
      });
  }

  private subscribeToNewJobs(): void {
    this.queueService
      .subscribeToDeadLetterEvents()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => {
        this.loadJobs();
      });
  }
}

export default DeadLetterQueueComponent;
