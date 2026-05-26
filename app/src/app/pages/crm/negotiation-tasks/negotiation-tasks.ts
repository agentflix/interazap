import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  inject,
  signal,
} from '@angular/core';
import { NgClass } from '@angular/common';
import { RouterLink } from '@angular/router';
import { type CdkDragDrop, DragDropModule } from '@angular/cdk/drag-drop';
import { LucideAngularModule } from 'lucide-angular';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { fromEvent } from 'rxjs';
import { filter } from 'rxjs/operators';
import { toast } from 'ngx-sonner';
import { PageTitle } from 'src/app/shared/components/page-title/page-title';
import { AuthStoreService } from 'src/app/core/services/auth-store.service';
import { ButtonComponent } from 'src/app/shared/components/button/button';
import {
  type NegotiationTask,
  NegotiationTaskService,
} from 'src/app/core/services/negotiation-task.service';
import { RealtimeService } from 'src/app/core/services/realtime.service';
import { formatDate } from '@shared/utils/string.utils';

const STATUS_COLUMNS = [
  {
    id: 'pending',
    label: 'Pendente',
    tone: 'border-l-4 border-l-warning/60',
    dot: 'bg-warning',
    badge: 'bg-warning/10 text-warning',
  },
  {
    id: 'in_progress',
    label: 'Em Progresso',
    tone: 'border-l-4 border-l-info/60',
    dot: 'bg-info',
    badge: 'bg-info/10 text-info',
  },
  {
    id: 'done',
    label: 'Concluída',
    tone: 'border-l-4 border-l-success/60',
    dot: 'bg-success',
    badge: 'bg-success/10 text-success',
  },
];

/**
 * Página de tarefas do CRM com visualização kanban (pendente, em progresso, concluída).
 * Suporta drag-and-drop entre colunas e atualização em tempo real via WebSocket.
 */
@Component({
  selector: 'app-negotiation-tasks',
  standalone: true,
  imports: [NgClass, RouterLink, DragDropModule, LucideAngularModule, PageTitle, ButtonComponent],
  templateUrl: './negotiation-tasks.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class NegotiationTasks {
  private readonly negotiationTaskService = inject(NegotiationTaskService);
  private readonly realtimeService = inject(RealtimeService);
  private readonly authStore = inject(AuthStoreService);
  private readonly destroyRef = inject(DestroyRef);

  readonly tasks = signal<NegotiationTask[]>([]);
  readonly isLoading = signal(false);
  readonly errorMessage = signal<string | null>(null);
  readonly columns = STATUS_COLUMNS;
  readonly dropListIds = this.columns.map((column) => `status-${column.id}`);

  readonly tasksByStatus = computed(() => {
    const grouped = new Map<string, NegotiationTask[]>();
    for (const column of this.columns) {
      grouped.set(column.id, []);
    }
    for (const task of this.tasks()) {
      const status = task.status || 'pending';
      if (!grouped.has(status)) {
        grouped.set(status, []);
      }
      grouped.get(status)!.push(task);
    }
    return grouped;
  });

  constructor() {
    this.loadTasks();
    this.listenRealtime();
    this.listenVisibility();
  }

  loadTasks(): void {
    this.isLoading.set(true);
    this.errorMessage.set(null);

    this.negotiationTaskService
      .listForUser()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.tasks.set(response.data.tasks ?? []);
          this.isLoading.set(false);
        },
        error: () => {
          this.errorMessage.set('Não foi possível carregar as tarefas.');
          this.isLoading.set(false);
        },
      });
  }

  getColumnTasks(status: string): NegotiationTask[] {
    return this.tasksByStatus().get(status) ?? [];
  }

  onDrop(event: CdkDragDrop<NegotiationTask[]>, status: string): void {
    if (event.previousContainer === event.container) return;

    const task = event.item.data as NegotiationTask;
    this.updateTaskStatus(task, status);
  }

  updateTaskStatus(task: NegotiationTask, status: string): void {
    if (!task.negotiation_id) return;

    const currentTask = this.tasks().find((item) => String(item.id) === String(task.id));
    const previousStatus = currentTask?.status || 'open';
    this.tasks.set(this.tasks().map((item) => (item.id === task.id ? { ...item, status } : item)));

    this.negotiationTaskService
      .updateStatus(task.negotiation_id, task.id, status)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          const updated = response.data;
          this.tasks.set(
            this.tasks().map((item) => (item.id === task.id ? { ...item, ...updated } : item)),
          );
          toast.success('Status da tarefa atualizado.');
        },
        error: () => {
          this.tasks.set(
            this.tasks().map((item) =>
              item.id === task.id ? { ...item, status: previousStatus } : item,
            ),
          );
          toast.error('Não foi possível atualizar o status da tarefa.');
        },
      });
  }

  formatDate(value?: string | null): string {
    return formatDate(value);
  }

  formatTime(value?: string | null): string {
    if (!value) return '-';
    return value.length >= 5 ? value.slice(0, 5) : value;
  }

  getStatusBadge(status?: string | null): string {
    return (
      this.columns.find((column) => column.id === (status || 'pending'))?.badge ??
      'bg-warning/10 text-warning'
    );
  }

  getStatusLabel(status?: string | null): string {
    return this.columns.find((column) => column.id === (status || 'pending'))?.label ?? 'Pendente';
  }

  getNegotiationLabel(task: NegotiationTask): string {
    const companyName = task.negotiation?.crm_company?.name;
    const title = task.negotiation?.title;
    if (companyName && title) {
      return `${companyName} · ${title}`;
    }
    return title || companyName || 'Sem negociação';
  }

  private listenRealtime(): void {
    this.realtimeService.connect();
    this.realtimeService
      .on<{
        company_id?: string | number;
        negotiation_id?: string | number;
        action?: string;
        task?: NegotiationTask;
        task_id?: string | number;
      }>('negotiation.task.changed')
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((payload) => {
        const task = payload.task;

        if (payload.action === 'deleted' && payload.task_id) {
          this.tasks.set(
            this.tasks().filter((item) => String(item.id) !== String(payload.task_id)),
          );
          return;
        }

        if (task) {
          const current = this.tasks();
          const index = current.findIndex((item) => String(item.id) === String(task.id));
          if (index >= 0) {
            this.tasks.set(
              current.map((item) =>
                String(item.id) === String(task.id) ? { ...item, ...task } : item,
              ),
            );
            return;
          }

          const userId = this.authStore.user()?.id;
          const isForUser = !userId
            ? true
            : String(task.assigned_to ?? task.user_id ?? '') === String(userId);

          if (isForUser) {
            this.tasks.set([task, ...current]);
          }
        } else {
          this.loadTasks();
        }
      });
  }

  private listenVisibility(): void {
    if (typeof document === 'undefined') return;

    fromEvent(document, 'visibilitychange')
      .pipe(
        filter(() => document.visibilityState === 'visible'),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe(() => this.loadTasks());
  }
}
