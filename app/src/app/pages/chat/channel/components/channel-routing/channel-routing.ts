import type {
  OnInit} from '@angular/core';
import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  effect,
  inject,
  input,
  output,
  signal,
} from '@angular/core';
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfAlertComponent,
  AfButtonComponent,
  AfLoadingButtonComponent,
  AfModalComponent,
  AfSelectInputComponent,
  AfSwitchInputComponent,
  AfTextInputComponent,
} from '@shared/components';
import { UserService } from '@core/services/user.service';
import { type User } from '@core/models/user.model';
import {
  ChatRoutingQueueService,
  type ChatRoutingQueueAgent,
} from '../../../services/chat-routing-queue.service';
import { RoutingAgentListComponent } from '../../../configuration/components/routing-agent-list/routing-agent-list';
import { RoutingAgentFormComponent } from '../../../configuration/components/routing-agent-form/routing-agent-form';

/**
 * Modal para sobrescrever a configuração global da fila de roteamento por canal.
 *
 * Componente standalone que usa signals e primitivas da UI compartilhada.
 * Permite ativar override, alterar estratégia, adicionar/remover/reordenar agentes.
 */
@Component({
  selector: 'app-channel-routing',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    LucideAngularModule,
    AfModalComponent,
    AfAlertComponent,
    AfButtonComponent,
    AfLoadingButtonComponent,
    AfSwitchInputComponent,
    AfSelectInputComponent,
    AfTextInputComponent,
    RoutingAgentListComponent,
    RoutingAgentFormComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './channel-routing.html',
})
export class ChannelRoutingComponent implements OnInit {
  private readonly service = inject(ChatRoutingQueueService);
  private readonly userService = inject(UserService);
  private readonly destroyRef = inject(DestroyRef);

  /** Identificador do canal. */
  readonly channelId = input.required<string>();

  /** Nome do canal exibido no título do modal (opcional). */
  readonly channelName = input<string>('');

  /** Emitido quando o modal solicita fechar sem persistir. */
  readonly closed = output<void>();

  /** Indica se o canal possui fila de roteamento própria (override ativo). */
  readonly overrideEnabled = signal(false);

  /** Controla a visibilidade do formulário de adição de agente. */
  readonly showAddForm = signal(false);

  /** Usuários ativos disponíveis para adicionar como agentes. */
  readonly users = signal<User[]>([]);

  /** Indica se uma operação de salvamento está em andamento. */
  readonly isSaving = signal(false);

  /** Estado local do toggle "fila habilitada" (não persiste até salvar). */
  readonly isEnabledLocal = signal(false);

  /** Dados da fila de roteamento do serviço. */
  readonly queue = this.service.queue;

  /** Agentes da fila do serviço. */
  readonly agents = this.service.agents;

  /** Estado de carregamento do serviço. */
  readonly loading = this.service.loading;

  /** Estado de erro do serviço. */
  readonly error = this.service.error;

  readonly strategyControl = new FormControl<'round_robin' | 'least_busy' | 'skill_based'>('round_robin', {
    nonNullable: true,
  });

  readonly strategyOptions = [
    { value: 'round_robin', label: 'Round Robin (Rodízio)' },
    { value: 'least_busy', label: 'Menor Carga' },
    { value: 'skill_based', label: 'Por Habilidade' },
  ];

  readonly maxOpenTicketsControl = new FormControl<number | null>(null, {
    nonNullable: false,
  });

  constructor() {
    effect(() => {
      const q = this.queue();
      if (q) {
        this.overrideEnabled.set(true);
        this.isEnabledLocal.set(q.is_enabled);
        this.strategyControl.setValue(q.strategy as 'round_robin' | 'least_busy' | 'skill_based', { emitEvent: false });
        this.maxOpenTicketsControl.setValue(q.max_open_tickets_per_agent, { emitEvent: false });
      } else {
        this.overrideEnabled.set(false);
        this.isEnabledLocal.set(false);
        this.maxOpenTicketsControl.setValue(null, { emitEvent: false });
      }
    });

    this.strategyControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => {
        // Local change only; persisted on save.
      });
  }

  ngOnInit(): void {
    this.service.loadForChannel(this.channelId());
    this.loadUsers();
  }

  private loadUsers(): void {
    this.userService
      .list({ is_active: true, per_page: 100 })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.users.set(response.data);
        },
        error: () => {
          // Users load failure is non-critical; the add form will show empty.
        },
      });
  }

  /** Alterna o estado de override localmente. */
  toggleOverride(): void {
    this.overrideEnabled.update((v) => !v);
    if (!this.overrideEnabled()) {
      this.showAddForm.set(false);
    }
  }

  /** Alterna o estado local de habilitação da fila (persiste ao salvar). */
  toggleEnabled(): void {
    this.isEnabledLocal.update((v) => !v);
  }

  /** Persiste a configuração atual de roteamento do canal. */
  onSave(): void {
    const id = this.channelId();
    const enabled = this.overrideEnabled();

    this.isSaving.set(true);

    if (enabled) {
      const data: Partial<Parameters<ChatRoutingQueueService['save']>[1]> = {
        is_enabled: this.isEnabledLocal(),
        strategy: this.strategyControl.value,
      };
      if (this.strategyControl.value === 'least_busy') {
        data.max_open_tickets_per_agent = this.maxOpenTicketsControl.value;
      }
      this.service.save('channel', data, id);
    } else {
      const current = this.queue();
      if (current) {
        this.service.save('channel', { is_enabled: false }, id);
      }
    }

    // The service performs async operations; close the modal optimistically
    // after a short delay to allow the request to fire.
    window.setTimeout(() => {
      this.isSaving.set(false);
      this.onClose();
    }, 300);
  }

  /** Adiciona um agente à fila do canal. */
  onAddAgent(userId: string, position?: number): void {
    this.service.addAgent('channel', userId, position, this.channelId());
    this.showAddForm.set(false);
  }

  /** Remove um agente da fila do canal. */
  onRemoveAgent(userId: string): void {
    this.service.removeAgent('channel', userId, this.channelId());
  }

  /** Reordena os agentes na fila do canal. */
  onReorder(agents: ChatRoutingQueueAgent[]): void {
    const payload = agents.map((a, index) => ({
      user_id: a.user_id,
      position: index + 1,
    }));
    this.service.reorder('channel', payload, this.channelId());
  }

  /** Alterna o estado ativo de um agente na fila do canal. */
  onToggleActive(userId: string, isActive: boolean): void {
    const currentAgents = this.agents();
    const updatedAgents = currentAgents.map((a: ChatRoutingQueueAgent) =>
      a.user_id === userId ? { ...a, is_active: isActive } : a,
    );
    this.service.save('channel', { agents: updatedAgents }, this.channelId());
  }

  /** Adiciona uma habilidade a um agente da fila do canal. */
  onAddSkill(userId: string, skill: string): void {
    this.service.addAgentSkill('channel', userId, skill, this.channelId());
  }

  /** Remove uma habilidade de um agente da fila do canal. */
  onRemoveSkill(userId: string, skill: string): void {
    this.service.removeAgentSkill('channel', userId, skill, this.channelId());
  }

  /** Fecha o modal sem efeitos colaterais. */
  onClose(): void {
    this.closed.emit();
  }

  /** Tenta recarregar a fila de roteamento após erro. */
  retryLoad(): void {
    this.service.loadForChannel(this.channelId());
  }
}
