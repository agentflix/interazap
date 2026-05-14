import type {
  OnInit} from '@angular/core';
import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  effect,
  inject,
  signal,
} from '@angular/core';
import { FormBuilder, FormControl, ReactiveFormsModule, Validators } from '@angular/forms';
import { takeUntilDestroyed, toSignal } from '@angular/core/rxjs-interop';
import { map, startWith } from 'rxjs';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfButtonComponent,
  AfEmptyStateComponent,
  AfLoadingButtonComponent,
  AfPageTitleComponent,
  AfRadioInputComponent,
  AfSelectInputComponent,
  AfSwitchInputComponent,
  AfTextareaInputComponent,
  AfTextInputComponent,
} from '@shared/components';
import { AuthStoreService } from '@core/services/auth-store.service';
import { TenantSettingsService } from '@core/services/tenant-settings.service';
import { ToastService } from '@core/services/toast.service';
import { UserService, type User } from '@core/services/user.service';
import type { TenantChatAutoCloseSettings } from '@shared/models/tenant-settings.model';
import {
  ChatRoutingQueueService,
  type ChatRoutingQueueAgent,
} from '../services/chat-routing-queue.service';
import { RoutingAgentListComponent } from './components/routing-agent-list/routing-agent-list';
import { RoutingAgentFormComponent } from './components/routing-agent-form/routing-agent-form';

@Component({
  selector: 'app-chat-configuration',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    LucideAngularModule,
    AfPageTitleComponent,
    AfEmptyStateComponent,
    AfButtonComponent,
    AfLoadingButtonComponent,
    AfSwitchInputComponent,
    AfSelectInputComponent,
    AfRadioInputComponent,
    AfTextareaInputComponent,
    AfTextInputComponent,
    RoutingAgentListComponent,
    RoutingAgentFormComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './chat-configuration.html',
})
export class ChatConfigurationPage implements OnInit {
  // ── Routing services ─────────────────────────────────────────────────────
  private readonly routingService = inject(ChatRoutingQueueService);
  private readonly userService = inject(UserService);

  // ── Auto-close services ──────────────────────────────────────────────────
  private readonly settingsService = inject(TenantSettingsService);
  private readonly authStore = inject(AuthStoreService);
  private readonly fb = inject(FormBuilder);

  private readonly toast = inject(ToastService);
  private readonly destroyRef = inject(DestroyRef);

  // ── Routing state ────────────────────────────────────────────────────────
  readonly showAddForm = signal(false);
  readonly users = signal<User[]>([]);

  readonly queue = this.routingService.queue;
  readonly agents = this.routingService.agents;
  readonly loading = this.routingService.loading;
  readonly error = this.routingService.error;

  readonly routingEnabledControl = new FormControl<boolean>(false, { nonNullable: true });

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
    validators: [Validators.min(1)],
  });

  // ── Auto-close state ─────────────────────────────────────────────────────
  readonly isAutoCloseLoading = signal(false);
  readonly isAutoCloseSaving = signal(false);
  readonly autoCloseError = signal<string | null>(null);

  readonly chatForm = this.fb.group({
    auto_close_inactivity_enabled: this.fb.control(false, { nonNullable: true }),
    auto_close_inactivity_minutes: this.fb.control(30, {
      nonNullable: true,
      validators: [Validators.required],
    }),
    auto_close_inactivity_target: this.fb.control<'both' | 'client' | 'agent'>('both', {
      nonNullable: true,
      validators: [Validators.required],
    }),
    auto_close_inactivity_message: this.fb.control(
      'Este atendimento foi encerrado automaticamente por inatividade. Caso precise de mais ajuda, por favor inicie um novo atendimento.',
      { nonNullable: true, validators: [Validators.maxLength(2000)] },
    ),
  });

  readonly isAutoCloseEnabled = toSignal(
    this.chatForm.controls.auto_close_inactivity_enabled.valueChanges.pipe(
      startWith(this.chatForm.controls.auto_close_inactivity_enabled.value),
      map((enabled) => enabled === true),
    ),
    { initialValue: false },
  );

  // ── Options ──────────────────────────────────────────────────────────────
  readonly inactivityMinutesOptions = [
    { value: 5, label: 'Após 5 minutos' },
    { value: 10, label: 'Após 10 minutos' },
    { value: 15, label: 'Após 15 minutos' },
    { value: 30, label: 'Após 30 minutos' },
    { value: 45, label: 'Após 45 minutos' },
    { value: 60, label: 'Após 1 hora' },
    { value: 120, label: 'Após 2 horas' },
  ];

  readonly inactivityTargetOptions = [
    { value: 'both', label: 'Ambos (atendente e cliente)' },
    { value: 'client', label: 'Apenas cliente' },
    { value: 'agent', label: 'Apenas atendente' },
  ];

  // ── Constructor ──────────────────────────────────────────────────────────
  constructor() {
    // Sync routing toggle, strategy & max_open with API state
    effect(() => {
      const q = this.queue();
      if (q) {
        this.routingEnabledControl.setValue(q.is_enabled, { emitEvent: false });
        this.strategyControl.setValue(q.strategy as 'round_robin' | 'least_busy' | 'skill_based', { emitEvent: false });
        this.maxOpenTicketsControl.setValue(q.max_open_tickets_per_agent, { emitEvent: false });
      }
    });

    this.routingEnabledControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((enabled) => {
        const exists = this.queue() !== null;
        const payload: Record<string, unknown> = { is_enabled: enabled };
        if (!exists) {
          payload['name'] = 'Global';
          payload['strategy'] = this.strategyControl.value;
          if (this.strategyControl.value === 'least_busy' && this.maxOpenTicketsControl.value !== null) {
            payload['max_open_tickets_per_agent'] = this.maxOpenTicketsControl.value;
          }
        }
        this.routingService.save('global', payload);
      });

    this.strategyControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((strategy) => {
        this.onStrategyChange(strategy);
      });

    this.maxOpenTicketsControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((value) => {
        if (this.maxOpenTicketsControl.invalid) {
          return;
        }
        const exists = this.queue() !== null;
        const payload: Record<string, unknown> = { max_open_tickets_per_agent: value };
        if (!exists) {
          payload['name'] = 'Global';
          payload['strategy'] = this.strategyControl.value;
          payload['is_enabled'] = this.routingEnabledControl.value;
        }
        this.routingService.save('global', payload);
      });
  }

  // ── Lifecycle ────────────────────────────────────────────────────────────
  ngOnInit(): void {
    this.routingService.loadGlobal();
    this.loadUsers();
    this.loadAutoCloseSettings();
  }

  // ── Auto-close: load ─────────────────────────────────────────────────────
  loadAutoCloseSettings(): void {
    const tenantId = this.authStore.user()?.tenant_id;
    if (!tenantId) {
      this.autoCloseError.set('Não foi possível identificar o inquilino.');
      return;
    }

    this.isAutoCloseLoading.set(true);
    this.autoCloseError.set(null);

    this.settingsService
      .getSettings(String(tenantId))
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          if (response.data.settings_chat) {
            this.chatForm.patchValue(response.data.settings_chat);
          }
          this.isAutoCloseLoading.set(false);
        },
        error: (err) => {
          this.autoCloseError.set(
            err.error?.message ?? 'Não foi possível carregar as configurações de auto-fechamento.',
          );
          this.isAutoCloseLoading.set(false);
        },
      });
  }

  // ── Auto-close: save ─────────────────────────────────────────────────────
  protected saveAutoClose(): void {
    if (this.isAutoCloseSaving()) {
      return;
    }

    const tenantId = this.authStore.user()?.tenant_id;
    if (!tenantId) {
      this.toast.error('Não foi possível identificar o inquilino.');
      return;
    }

    if (this.isAutoCloseEnabled()) {
      this.chatForm.markAllAsTouched();
      if (this.chatForm.invalid) {
        return;
      }
    }

    this.isAutoCloseSaving.set(true);

    this.settingsService
      .updateSettings(String(tenantId), {
        settings_chat: this.chatForm.getRawValue() as TenantChatAutoCloseSettings,
      })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.isAutoCloseSaving.set(false);
          this.toast.success('Configurações de auto-fechamento salvas com sucesso!');
        },
        error: (err) => {
          this.autoCloseError.set(
            err.error?.message ?? 'Não foi possível salvar as configurações.',
          );
          this.isAutoCloseSaving.set(false);
        },
      });
  }

  // ── Routing: users ───────────────────────────────────────────────────────
  private loadUsers(): void {
    this.userService
      .list({ is_active: true, per_page: 100 })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.users.set(response.data);
        },
        error: () => {
          this.toast.error('Erro ao carregar usuários.');
        },
      });
  }

  protected retryLoad(): void {
    this.routingService.loadGlobal();
  }

  protected onStrategyChange(strategy: string): void {
    const exists = this.queue() !== null;
    const payload: Record<string, unknown> = { strategy: strategy as 'round_robin' | 'least_busy' | 'skill_based' };
    if (!exists) {
      payload['name'] = 'Global';
      payload['is_enabled'] = this.routingEnabledControl.value;
    }
    if (strategy === 'least_busy' && this.maxOpenTicketsControl.value !== null) {
      payload['max_open_tickets_per_agent'] = this.maxOpenTicketsControl.value;
    }
    this.routingService.save('global', payload);
  }

  protected onAddAgent(userId: string, position?: number): void {
    this.routingService.addAgent('global', userId, position);
    this.showAddForm.set(false);
  }

  protected onRemoveAgent(userId: string): void {
    this.routingService.removeAgent('global', userId);
  }

  protected onReorder(agents: ChatRoutingQueueAgent[]): void {
    const payload = agents.map((a, index) => ({
      user_id: a.user_id,
      position: index + 1,
    }));
    this.routingService.reorder('global', payload);
  }

  protected onToggleActive(userId: string, isActive: boolean): void {
    const currentAgents = this.agents();
    const updatedAgents = currentAgents.map((a: ChatRoutingQueueAgent) =>
      a.user_id === userId ? { ...a, is_active: isActive } : a,
    );
    this.routingService.save('global', { agents: updatedAgents });
  }

  protected onAddSkill(userId: string, skill: string): void {
    this.routingService.addAgentSkill('global', userId, skill);
  }

  protected onRemoveSkill(userId: string, skill: string): void {
    this.routingService.removeAgentSkill('global', userId, skill);
  }
}
