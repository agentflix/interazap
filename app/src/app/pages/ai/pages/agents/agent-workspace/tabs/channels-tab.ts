import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  effect,
  inject,
  input,
  signal,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { LucideAngularModule } from 'lucide-angular';
// AfLoadingButtonComponent removed — save is handled globally
import { ToastService } from '@core/services/toast.service';
import { type AiAgent, type AiAgentChannel } from '@ai/models/ai.model';
import { AiAgentService } from '@ai/services/ai-agent.service';

/**
 * Channels tab — select which channels the agent handles.
 * Uses the agent update endpoint (no separate channels API).
 */
@Component({
  selector: 'app-agent-channels-tab',
  standalone: true,
  imports: [LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './channels-tab.html',
})
export class AgentChannelsTabComponent {
  private readonly agentService = inject(AiAgentService);
  private readonly toast = inject(ToastService);
  private readonly destroyRef = inject(DestroyRef);

  readonly agent = input.required<AiAgent>();
  readonly enabledChannels = signal<AiAgentChannel[]>([]);

  readonly channelOptions: {
    key: AiAgentChannel;
    label: string;
    description: string;
    icon: string;
    iconClass: string;
    activeIconClass: string;
    disabled: boolean;
  }[] = [
    {
      key: 'whatsapp',
      label: 'WhatsApp',
      description: 'Atendimento via WhatsApp Business API',
      icon: 'message-circle',
      iconClass: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
      activeIconClass: 'bg-green-500 text-white dark:bg-green-600',
      disabled: false,
    },
    {
      key: 'webchat',
      label: 'Webchat',
      description: 'Widget de chat no site',
      icon: 'globe',
      iconClass: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
      activeIconClass: 'bg-blue-500 text-white dark:bg-blue-600',
      disabled: true,
    },
    {
      key: 'email',
      label: 'E-mail',
      description: 'Respostas automáticas via e-mail',
      icon: 'mail',
      iconClass: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
      activeIconClass: 'bg-amber-500 text-white dark:bg-amber-600',
      disabled: true,
    },
    {
      key: 'internal',
      label: 'Interno',
      description: 'Agente disponível apenas via API interna',
      icon: 'lock',
      iconClass: 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400',
      activeIconClass: 'bg-neutral-600 text-white dark:bg-neutral-500',
      disabled: true,
    },
  ];

  constructor() {
    effect(() => {
      const agent = this.agent();
      const existing = agent.channels ?? [];

      // If the agent has no channels at all, pre-select whatsapp by default as requested.
      if (existing.length === 0) {
        this.enabledChannels.set(['whatsapp']);
      } else {
        this.enabledChannels.set(existing);
      }
    });
  }

  /**
   * Check if a channel is currently enabled.
   */
  isEnabled(channel: AiAgentChannel): boolean {
    return this.enabledChannels().includes(channel);
  }

  /**
   * Toggle a channel on/off.
   */
  toggleChannel(channel: AiAgentChannel): void {
    const current = this.enabledChannels();
    if (current.includes(channel)) {
      this.enabledChannels.set(current.filter((c) => c !== channel));
    } else {
      this.enabledChannels.set([...current, channel]);
    }
  }

  // Save method removed in favor of global save
}
