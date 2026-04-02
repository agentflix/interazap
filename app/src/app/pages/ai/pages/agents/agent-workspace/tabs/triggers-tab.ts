import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  inject,
  input,
  signal,
  type OnInit,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfButtonComponent,
  AfLoadingButtonComponent,
  AfModalComponent,
  AfSelectInputComponent,
  AfStatusBadgeComponent,
  AfSwitchInputComponent,
  AfTextInputComponent,
  type AfSelectOption,
} from '@shared/components';
import { ToastService } from '@core/services/toast.service';
import {
  type AiAgentTrigger,
  type AiAgentTriggerConfig,
  type AiAgentTriggerPayload,
  type AiTriggerType,
} from '@ai/models/ai.model';
import { AiAgentService } from '@ai/services/ai-agent.service';

/**
 * Triggers tab — CRUD for agent triggers (cron, webhook, event).
 */
@Component({
  selector: 'app-agent-triggers-tab',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    LucideAngularModule,
    AfButtonComponent,
    AfLoadingButtonComponent,
    AfModalComponent,
    AfTextInputComponent,
    AfSelectInputComponent,
    AfSwitchInputComponent,
    AfStatusBadgeComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './triggers-tab.html',
})
export class AgentTriggersTabComponent implements OnInit {
  private readonly agentService = inject(AiAgentService);
  private readonly toast = inject(ToastService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly fb = inject(FormBuilder);

  readonly agentId = input.required<string>();
  readonly triggers = signal<AiAgentTrigger[]>([]);
  readonly loading = signal(false);
  readonly error = signal<string | null>(null);
  readonly saving = signal(false);
  readonly showModal = signal(false);
  readonly editingTrigger = signal<AiAgentTrigger | null>(null);

  readonly triggerForm = this.fb.nonNullable.group({
    name: ['', [Validators.required, Validators.minLength(2)]],
    trigger_type: ['cron' as AiTriggerType, [Validators.required]],
    is_active: [true],
    cron_expression: [''],
    webhook_url: [''],
    event_name: [''],
  });

  readonly triggerTypeOptions: AfSelectOption[] = [
    { value: 'cron', label: 'Cron (agendamento)' },
    { value: 'webhook', label: 'Webhook' },
    { value: 'event', label: 'Evento' },
  ];

  readonly eventOptions: AfSelectOption[] = [
    { value: 'message.created', label: 'Nova Mensagem Recebida' },
    { value: 'ticket.updated', label: 'Ticket Atualizado' },
    { value: 'contact.created', label: 'Novo Contato Criado' },
    { value: 'lead.qualified', label: 'Lead Qualificado' },
  ];

  ngOnInit(): void {
    this.loadTriggers();
  }

  /**
   * Format trigger type label.
   */
  formatTriggerType(type: AiTriggerType): string {
    const labels: Record<AiTriggerType, string> = {
      cron: 'Agendamento',
      webhook: 'Webhook',
      event: 'Evento',
    };
    return labels[type] ?? type;
  }

  /**
   * Get icon name for trigger type.
   */
  triggerIcon(type: AiTriggerType): string {
    const icons: Record<AiTriggerType, string> = {
      cron: 'clock',
      webhook: 'webhook',
      event: 'zap',
    };
    return icons[type] ?? 'zap';
  }

  /**
   * Get icon class for trigger type.
   */
  triggerIconClass(type: AiTriggerType): string {
    const classes: Record<string, string> = {
      cron: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
      webhook: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
      event: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    };
    return (
      classes[type] ?? 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400'
    );
  }

  /**
   * Open modal for creating a trigger.
   */
  openCreateModal(): void {
    this.editingTrigger.set(null);
    this.triggerForm.reset({
      name: this.fallbackTriggerName('cron'),
      trigger_type: 'cron',
      is_active: true,
      cron_expression: '',
      webhook_url: '',
      event_name: '',
    });
    this.showModal.set(true);
  }

  /**
   * Open modal for editing a trigger.
   */
  openEditModal(trigger: AiAgentTrigger): void {
    const normalized = this.normalizeTrigger(trigger);

    this.editingTrigger.set(trigger);
    this.triggerForm.patchValue({
      name: normalized.name ?? '',
      trigger_type: normalized.type,
      is_active: normalized.status === 'active',
      cron_expression: normalized.config.cron_expression ?? '',
      webhook_url: normalized.config.webhook_url ?? '',
      event_name: normalized.config.event_name ?? '',
    });
    this.showModal.set(true);
  }

  /**
   * Close the modal.
   */
  closeModal(): void {
    this.showModal.set(false);
    this.editingTrigger.set(null);
  }

  /**
   * Save (create or update) trigger.
   */
  saveTrigger(): void {
    if (this.triggerForm.invalid) return;

    this.saving.set(true);
    const formVal = this.triggerForm.getRawValue();
    const triggerType = formVal.trigger_type;

    const config = this.buildTriggerConfig(triggerType, formVal);

    const payload: AiAgentTriggerPayload = {
      type: triggerType,
      config,
      status: formVal.is_active === true ? 'active' : 'inactive',
    };

    const editing = this.editingTrigger();
    const request$ =
      editing !== null
        ? this.agentService.updateTrigger(this.agentId(), editing.id, payload)
        : this.agentService.createTrigger(this.agentId(), payload);

    request$.pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: () => {
        this.saving.set(false);
        this.closeModal();
        this.toast.success(editing !== null ? 'Trigger atualizado.' : 'Trigger criado.');
        this.loadTriggers();
      },
      error: () => {
        this.saving.set(false);
        this.toast.error('Erro ao salvar trigger.');
      },
    });
  }

  /**
   * Build config record from form values based on trigger type.
   */
  private buildTriggerConfig(
    type: AiTriggerType,
    formVal: {
      name: string;
      trigger_type: AiTriggerType;
      is_active: boolean;
      cron_expression: string;
      webhook_url: string;
      event_name: string;
    },
  ): AiAgentTriggerConfig {
    const editingConfig = this.editingTrigger()?.config ?? {};
    const baseConfig: AiAgentTriggerConfig = {
      ...editingConfig,
      name: formVal.name,
    };

    if (type === 'cron') {
      if (formVal.cron_expression !== '') {
        baseConfig.cron_expression = formVal.cron_expression;
      }
      delete baseConfig.webhook_url;
      delete baseConfig.event_name;
      return baseConfig;
    }

    if (type === 'webhook') {
      if (formVal.webhook_url !== '') {
        baseConfig.webhook_url = formVal.webhook_url;
      }
      delete baseConfig.cron_expression;
      delete baseConfig.event_name;
      return baseConfig;
    }

    if (formVal.event_name !== '') {
      baseConfig.event_name = formVal.event_name;
    }
    delete baseConfig.cron_expression;
    delete baseConfig.webhook_url;
    return baseConfig;
  }

  /**
   * Delete a trigger.
   */
  deleteTrigger(trigger: AiAgentTrigger): void {
    if (!confirm(`Excluir trigger "${this.resolveTriggerName(trigger)}"?`)) return;

    this.agentService
      .deleteTrigger(this.agentId(), trigger.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.toast.success('Trigger excluído.');
          this.loadTriggers();
        },
        error: () => this.toast.error('Erro ao excluir trigger.'),
      });
  }

  private loadTriggers(): void {
    const id = this.agentId();
    if (!id) return;

    this.loading.set(true);
    this.error.set(null);

    this.agentService
      .getTriggers(id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (triggers) => {
          this.triggers.set(triggers.map((trigger) => this.normalizeTrigger(trigger)));
          this.loading.set(false);
        },
        error: () => {
          this.error.set('Erro ao carregar triggers.');
          this.loading.set(false);
        },
      });
  }

  private normalizeTrigger(trigger: AiAgentTrigger): AiAgentTrigger {
    const config = trigger.config ?? {};

    return {
      ...trigger,
      config,
      name: this.resolveTriggerName(trigger),
    };
  }

  private resolveTriggerName(trigger: AiAgentTrigger): string {
    const configName = trigger.config?.name?.trim();
    if (configName !== undefined && configName.length > 0) {
      return configName;
    }

    const legacyName = trigger.name?.trim();
    if (legacyName !== undefined && legacyName.length > 0) {
      return legacyName;
    }

    return this.fallbackTriggerName(trigger.type);
  }

  private fallbackTriggerName(type: AiTriggerType): string {
    const names: Record<AiTriggerType, string> = {
      cron: 'Trigger Agendado',
      webhook: 'Trigger Webhook',
      event: 'Trigger Evento',
    };

    return names[type];
  }
}
