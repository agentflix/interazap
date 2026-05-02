import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  effect,
  inject,
  input,
  OnInit,
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
} from '@shared/components';
import { UserService, type User } from '@core/services/user.service';
import {
  ChatRoutingQueueService,
  type ChatRoutingQueueAgent,
} from '../../../services/chat-routing-queue.service';
import { RoutingAgentListComponent } from '../../../configuration/components/routing-agent-list/routing-agent-list';
import { RoutingAgentFormComponent } from '../../../configuration/components/routing-agent-form/routing-agent-form';

/**
 * Modal component for overriding the global routing queue configuration per channel.
 *
 * Standalone component using signals and shared UI primitives.
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

  /** Channel identifier */
  readonly channelId = input.required<string>();

  /** Optional channel name for the modal title */
  readonly channelName = input<string>('');

  /** Emitted when the modal requests to close without persisting */
  readonly closed = output<void>();

  /** Whether the channel has its own routing queue (override) */
  readonly overrideEnabled = signal(false);

  /** Toggle visibility of the add-agent form */
  readonly showAddForm = signal(false);

  /** Active users available to add as agents */
  readonly users = signal<User[]>([]);

  /** Whether a save operation is in progress */
  readonly isSaving = signal(false);

  /** Local mirror of the queue enabled state (does not persist until save) */
  readonly isEnabledLocal = signal(false);

  /** Queue data from the service */
  readonly queue = this.service.queue;

  /** Agents from the service */
  readonly agents = this.service.agents;

  /** Loading state from the service */
  readonly loading = this.service.loading;

  /** Error state from the service */
  readonly error = this.service.error;

  readonly strategyControl = new FormControl<'round_robin'>('round_robin', {
    nonNullable: true,
  });

  readonly strategyOptions = [{ value: 'round_robin', label: 'Round Robin (Rodízio)' }];

  constructor() {
    effect(() => {
      const q = this.queue();
      if (q) {
        this.overrideEnabled.set(true);
        this.isEnabledLocal.set(q.is_enabled);
        this.strategyControl.setValue(q.strategy as 'round_robin', { emitEvent: false });
      } else {
        this.overrideEnabled.set(false);
        this.isEnabledLocal.set(false);
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

  /** Toggle the override state locally */
  toggleOverride(): void {
    this.overrideEnabled.update((v) => !v);
    if (!this.overrideEnabled()) {
      this.showAddForm.set(false);
    }
  }

  /** Toggle the local enabled state (persisted on save) */
  toggleEnabled(): void {
    this.isEnabledLocal.update((v) => !v);
  }

  /** Persist the current channel routing configuration */
  onSave(): void {
    const id = this.channelId();
    const enabled = this.overrideEnabled();

    this.isSaving.set(true);

    if (enabled) {
      const data: Partial<Parameters<ChatRoutingQueueService['save']>[1]> = {
        is_enabled: this.isEnabledLocal(),
        strategy: this.strategyControl.value,
      };
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

  /** Add an agent to the channel queue */
  onAddAgent(userId: string, position?: number): void {
    this.service.addAgent('channel', userId, position, this.channelId());
    this.showAddForm.set(false);
  }

  /** Remove an agent from the channel queue */
  onRemoveAgent(userId: string): void {
    this.service.removeAgent('channel', userId, this.channelId());
  }

  /** Reorder agents in the channel queue */
  onReorder(agents: ChatRoutingQueueAgent[]): void {
    const payload = agents.map((a, index) => ({
      user_id: a.user_id,
      position: index + 1,
    }));
    this.service.reorder('channel', payload, this.channelId());
  }

  /** Toggle agent active state in the channel queue */
  onToggleActive(userId: string, isActive: boolean): void {
    const currentAgents = this.agents();
    const updatedAgents = currentAgents.map((a: ChatRoutingQueueAgent) =>
      a.user_id === userId ? { ...a, is_active: isActive } : a,
    );
    this.service.save('channel', { agents: updatedAgents }, this.channelId());
  }

  /** Close the modal without side effects */
  onClose(): void {
    this.closed.emit();
  }

  /** Retry loading the queue */
  retryLoad(): void {
    this.service.loadForChannel(this.channelId());
  }
}
