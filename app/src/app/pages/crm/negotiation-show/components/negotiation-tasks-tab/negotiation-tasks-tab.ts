import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  effect,
  inject,
  input,
  output,
  signal,
} from '@angular/core';
import { RouterLink } from '@angular/router';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ReactiveFormsModule, Validators, NonNullableFormBuilder } from '@angular/forms';
import { LucideAngularModule } from 'lucide-angular';
import { toast } from 'ngx-sonner';
import {
  type NegotiationTask,
  NegotiationTaskService,
} from 'src/app/core/services/negotiation-task.service';
import {
  ButtonComponent,
  IconButtonComponent,
  LoadingButtonComponent,
} from '@shared/components/buttons';
import { ConfirmModalComponent } from '@shared/components/confirm-modal/confirm-modal';
import { ModalComponent } from '@shared/components/modal/modal';
import {
  type SelectOption,
  SelectInputComponent,
  SwitchInputComponent,
  TextInputComponent,
  TextareaInputComponent,
} from '@shared/components/inputs';
import { type TaskActionOption, type TaskStatusOption } from '../../negotiation-show.model';
import { formatTimeInput } from '../../negotiation-show.utils';
import { formatDate } from '@shared/utils/string.utils';

/**
 * Tab content responsible for negotiation tasks CRUD.
 */
@Component({
  selector: 'app-negotiation-tasks-tab',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    RouterLink,
    LucideAngularModule,
    ButtonComponent,
    IconButtonComponent,
    LoadingButtonComponent,
    ModalComponent,
    ConfirmModalComponent,
    TextInputComponent,
    TextareaInputComponent,
    SelectInputComponent,
    SwitchInputComponent,
  ],
  templateUrl: './negotiation-tasks-tab.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class NegotiationTasksTabComponent {
  private readonly taskService = inject(NegotiationTaskService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly fb = inject(NonNullableFormBuilder);

  readonly negotiationId = input.required<string | number>();
  readonly tasks = input<NegotiationTask[]>([]);
  readonly isTasksLoading = input(false);
  readonly openCreateRequestToken = input(0);
  readonly tasksChanged = output<void>();
  readonly failed = output<string>();

  readonly isTaskModalOpen = signal(false);
  readonly isTaskSaving = signal(false);
  readonly taskModalError = signal<string | null>(null);
  readonly editingTask = signal<NegotiationTask | null>(null);
  readonly deletingTask = signal<NegotiationTask | null>(null);
  readonly isDeletingTask = signal(false);

  readonly taskForm = this.fb.group({
    title: this.fb.control('', Validators.required),
    description: this.fb.control(''),
    action_type: this.fb.control('follow_up', Validators.required),
    status: this.fb.control('pending', Validators.required),
    due_date: this.fb.control('', Validators.required),
    start_time: this.fb.control('', Validators.required),
    end_time: this.fb.control('', Validators.required),
    add_to_agenda: this.fb.control(false),
    notify_ui: this.fb.control(true),
    notify_email: this.fb.control(false),
    notify_push: this.fb.control(false),
    notify_whatsapp: this.fb.control(false),
  });

  readonly taskActionOptions: TaskActionOption[] = [
    { id: 'call', label: 'Ligar', icon: 'phone' },
    { id: 'email', label: 'Enviar email', icon: 'mail' },
    { id: 'whatsapp', label: 'WhatsApp', icon: 'message-square' },
    { id: 'meeting', label: 'Reunião', icon: 'users' },
    { id: 'proposal', label: 'Enviar proposta', icon: 'file-text' },
    { id: 'follow_up', label: 'Follow-up', icon: 'repeat' },
    { id: 'demo', label: 'Apresentação/Demo', icon: 'presentation' },
    { id: 'contract', label: 'Contrato', icon: 'handshake' },
    { id: 'payment', label: 'Cobrança/Pagamento', icon: 'credit-card' },
    { id: 'visit', label: 'Visita', icon: 'map-pin' },
    { id: 'other', label: 'Outro', icon: 'clipboard-check' },
  ];

  readonly taskStatusOptions: TaskStatusOption[] = [
    { id: 'pending', label: 'Pendente', tone: 'bg-warning/10 text-warning' },
    { id: 'in_progress', label: 'Em execução', tone: 'bg-info/10 text-info' },
    { id: 'done', label: 'Finalizada', tone: 'bg-success/10 text-success' },
  ];

  readonly taskActionSelectOptions: SelectOption[] = this.taskActionOptions.map((opt) => ({
    label: opt.label,
    value: opt.id,
  }));

  readonly taskStatusSelectOptions: SelectOption[] = this.taskStatusOptions.map((opt) => ({
    label: opt.label,
    value: opt.id,
  }));

  readonly taskCount = computed(() => this.tasks().length);

  private lastCreateRequestToken = 0;

  constructor() {
    effect(() => {
      const token = this.openCreateRequestToken();
      if (token > 0 && token !== this.lastCreateRequestToken) {
        this.lastCreateRequestToken = token;
        this.openTaskModal();
      }
    });
  }

  openTaskModal(task?: NegotiationTask): void {
    this.editingTask.set(task ?? null);
    this.taskModalError.set(null);
    this.isTaskModalOpen.set(true);

    let defaultStartTime = '';
    let defaultEndTime = '';
    let defaultDueDate = '';

    if (!task) {
      const now = new Date();
      const end = new Date(now.getTime() + 30 * 60000);

      const pad = (n: number) => n.toString().padStart(2, '0');

      defaultStartTime = `${pad(now.getHours())}:${pad(now.getMinutes())}`;
      defaultEndTime = `${pad(end.getHours())}:${pad(end.getMinutes())}`;
      defaultDueDate = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
    }

    this.taskForm.reset({
      title: task?.title ?? '',
      description: task?.description ?? '',
      action_type: task?.action_type ?? 'follow_up',
      status: task?.status ?? 'pending',
      due_date: task?.due_date ? this.formatDateInput(task.due_date) : defaultDueDate,
      start_time: task?.start_time ? formatTimeInput(task.start_time) : defaultStartTime,
      end_time: task?.end_time ? formatTimeInput(task.end_time) : defaultEndTime,
      add_to_agenda: task?.add_to_agenda ?? false,
      notify_ui: task?.notify_ui ?? true,
      notify_email: task?.notify_email ?? false,
      notify_push: task?.notify_push ?? false,
      notify_whatsapp: task?.notify_whatsapp ?? false,
    });
  }

  closeTaskModal(): void {
    this.isTaskModalOpen.set(false);
  }

  saveTask(): void {
    if (this.taskForm.invalid || this.isTaskSaving()) {
      this.taskForm.markAllAsTouched();
      return;
    }

    const value = this.taskForm.getRawValue();
    if (!value.due_date || !value.start_time || !value.end_time) {
      this.taskModalError.set('Informe data e horários da tarefa.');
      return;
    }
    if (value.end_time === value.start_time) {
      this.taskModalError.set('O horário de término não pode ser igual ao de início.');
      return;
    }

    if (
      value.add_to_agenda &&
      !value.notify_ui &&
      !value.notify_email &&
      !value.notify_push &&
      !value.notify_whatsapp
    ) {
      this.taskForm.patchValue({ notify_ui: true });
      this.taskModalError.set('Selecione pelo menos um canal de notificação.');
      return;
    }

    this.isTaskSaving.set(true);
    this.taskModalError.set(null);

    const editing = this.editingTask();
    const request = editing
      ? this.taskService.update(this.negotiationId(), editing.id, {
          title: value.title,
          description: value.description || undefined,
          status: value.status,
          action_type: value.action_type,
          due_date: value.due_date || null,
          start_time: value.start_time || null,
          end_time: value.end_time || null,
          add_to_agenda: value.add_to_agenda,
          notify_ui: value.notify_ui,
          notify_email: value.notify_email,
          notify_push: value.notify_push,
          notify_whatsapp: value.notify_whatsapp,
        })
      : this.taskService.create(this.negotiationId(), {
          title: value.title,
          description: value.description || undefined,
          status: value.status,
          action_type: value.action_type,
          due_date: value.due_date || null,
          start_time: value.start_time || null,
          end_time: value.end_time || null,
          add_to_agenda: value.add_to_agenda,
          notify_ui: value.notify_ui,
          notify_email: value.notify_email,
          notify_push: value.notify_push,
          notify_whatsapp: value.notify_whatsapp,
        });

    request.pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: () => {
        this.isTaskSaving.set(false);
        this.closeTaskModal();
        this.tasksChanged.emit();
        toast.success(editing ? 'Tarefa atualizada com sucesso.' : 'Tarefa criada com sucesso.');
      },
      error: (apiError) => {
        this.isTaskSaving.set(false);
        const apiMessage = apiError?.error?.errors?.schedule?.[0] ?? apiError?.error?.message;
        this.taskModalError.set(apiMessage || 'Não foi possível salvar a tarefa.');
      },
    });
  }

  toggleTask(task: NegotiationTask): void {
    this.taskService
      .toggle(this.negotiationId(), task.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => this.tasksChanged.emit(),
        error: () => this.failed.emit('Não foi possível atualizar a tarefa.'),
      });
  }

  updateTaskStatus(task: NegotiationTask, status: string): void {
    if (task.status === status) return;
    this.taskService
      .updateStatus(this.negotiationId(), task.id, status)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => this.tasksChanged.emit(),
        error: () => this.failed.emit('Não foi possível atualizar o status da tarefa.'),
      });
  }

  cycleTaskStatus(task: NegotiationTask): void {
    const currentStatus = task.status ?? 'pending';
    const orderedStatuses: ('pending' | 'in_progress' | 'done')[] = [
      'pending',
      'in_progress',
      'done',
    ];
    const currentIndex = orderedStatuses.indexOf(
      currentStatus as 'pending' | 'in_progress' | 'done',
    );
    const nextStatus = orderedStatuses[(currentIndex + 1) % orderedStatuses.length];
    this.updateTaskStatus(task, nextStatus);
  }

  confirmDeleteTask(task: NegotiationTask): void {
    this.deletingTask.set(task);
  }

  cancelDeleteTask(): void {
    this.deletingTask.set(null);
  }

  deleteTask(): void {
    const task = this.deletingTask();
    if (!task || this.isDeletingTask()) return;

    this.isDeletingTask.set(true);
    this.taskService
      .delete(this.negotiationId(), task.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.isDeletingTask.set(false);
          this.deletingTask.set(null);
          this.tasksChanged.emit();
        },
        error: () => {
          this.isDeletingTask.set(false);
          this.failed.emit('Não foi possível remover a tarefa.');
        },
      });
  }

  getTaskActionMeta(actionType?: string | null): TaskActionOption {
    if (!actionType) return this.taskActionOptions[this.taskActionOptions.length - 1];
    return (
      this.taskActionOptions.find((option) => option.id === actionType) ??
      this.taskActionOptions[this.taskActionOptions.length - 1]
    );
  }

  getTaskStatusMeta(status?: string | null): TaskStatusOption {
    if (!status) return this.taskStatusOptions[0];
    return (
      this.taskStatusOptions.find((option) => option.id === status) ?? this.taskStatusOptions[0]
    );
  }

  formatDate(value?: string | null): string {
    return formatDate(value);
  }

  formatDateInput(value: string): string {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    return date.toISOString().split('T')[0];
  }

  formatTimeInput(value: string): string {
    return formatTimeInput(value);
  }
}
