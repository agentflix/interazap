import { CommonModule } from '@angular/common';
import { ChangeDetectionStrategy, Component, input, output } from '@angular/core';
import { type Funnel, type FunnelStep } from 'src/app/core/services/funnel.service';
import { type Negotiation } from 'src/app/core/services/negotiation.service';
import { type NegotiationTask } from 'src/app/core/services/negotiation-task.service';

import type { NotifyChannel, NegotiationDetailForm, NegotiationTaskForm } from './negotiation-actions.component.model';
export * from './negotiation-actions.component.model';



/** Evento de alteração do formulário de detalhes. */
interface DetailFieldChangeEvent {
  field: keyof NegotiationDetailForm;
  value: string;
}

/** Evento de alteração do formulário de tarefas. */
export type TaskFieldChangeEvent =
  | {
      field: 'notifyChannel';
      value: NotifyChannel;
    }
  | {
      field: Exclude<keyof NegotiationTaskForm, 'notifyChannel'>;
      value: string;
    };

/**
 * Painel de ações da negociação: edição de detalhes e criação de tarefas.
 *
 * @remarks
 * Componente presentacional puro — recebe todos os dados via inputs e emite
 * eventos para o container gerenciar estado. Exibe formulário de detalhes
 * (funil, etapa, data prevista) e lista de tarefas com criação inline.
 */
@Component({
  selector: 'app-negotiation-actions',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './negotiation-actions.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class NegotiationActionsComponent {
  readonly selectedNegotiation = input.required<Negotiation>();
  readonly detailForm = input.required<NegotiationDetailForm>();
  readonly funnels = input<Funnel[]>([]);
  readonly detailSteps = input<FunnelStep[]>([]);
  readonly isSavingDetails = input<boolean>(false);
  readonly canSaveDetails = input<boolean>(false);

  readonly tasks = input<NegotiationTask[]>([]);
  readonly isTasksLoading = input<boolean>(false);
  readonly tasksError = input<string | null>(null);
  readonly completingTaskId = input<string | number | null>(null);
  readonly taskForm = input.required<NegotiationTaskForm>();
  readonly isSavingTask = input<boolean>(false);
  readonly taskFormValid = input<boolean>(false);

  readonly detailFieldChange = output<DetailFieldChangeEvent>();
  readonly saveDetails = output<void>();
  readonly taskFieldChange = output<TaskFieldChangeEvent>();
  readonly createTask = output<void>();
  readonly toggleTask = output<NegotiationTask>();

  /** Formata uma data ISO para o padrão brasileiro (dd/mm/aaaa). */
  formatDate(value?: string | null): string {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '-';
    return date.toLocaleDateString('pt-BR');
  }

  /** Retorna as classes CSS de cor para o status da negociação (won/lost/padrão). */
  statusTone(status?: string | null): string {
    switch (status) {
      case 'won':
        return 'bg-success/10 text-success';
      case 'lost':
        return 'bg-danger/10 text-danger';
      default:
        return 'bg-primary/10 text-primary';
    }
  }

  onDetailSelectChange(field: keyof NegotiationDetailForm, event: Event): void {
    this.detailFieldChange.emit({
      field,
      value: this.readSelectValue(event),
    });
  }

  onDetailInputChange(field: keyof NegotiationDetailForm, event: Event): void {
    this.detailFieldChange.emit({
      field,
      value: this.readInputValue(event),
    });
  }

  /** Emite o evento de salvar detalhes da negociação. */
  emitSaveDetails(): void {
    this.saveDetails.emit();
  }

  onTaskTextInput(field: Exclude<keyof NegotiationTaskForm, 'notifyChannel'>, event: Event): void {
    this.taskFieldChange.emit({
      field,
      value: this.readInputValue(event),
    });
  }

  onTaskSelectChange(field: keyof NegotiationTaskForm, event: Event): void {
    if (field === 'notifyChannel') {
      this.taskFieldChange.emit({
        field,
        value: this.normalizeNotifyChannel(this.readSelectValue(event)),
      });
      return;
    }

    this.taskFieldChange.emit({
      field,
      value: this.readSelectValue(event),
    });
  }

  /** Emite o evento de criar tarefa. */
  emitCreateTask(): void {
    this.createTask.emit();
  }

  /** Emite o evento de alternar o estado de conclusão de uma tarefa. */
  emitToggleTask(task: NegotiationTask): void {
    this.toggleTask.emit(task);
  }

  private readInputValue(event: Event): string {
    return (event.target as HTMLInputElement | null)?.value ?? '';
  }

  private readSelectValue(event: Event): string {
    return (event.target as HTMLSelectElement | null)?.value ?? '';
  }

  private normalizeNotifyChannel(value: string): NotifyChannel {
    switch (value) {
      case 'whatsapp':
      case 'email':
      case 'push':
      case 'none':
        return value;
      default:
        return 'none';
    }
  }
}
