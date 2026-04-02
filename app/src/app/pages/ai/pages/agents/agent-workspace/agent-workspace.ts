import {
  type OnInit,
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  inject,
  signal,
  viewChild,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { lastValueFrom } from 'rxjs';
import { ActivatedRoute, Router } from '@angular/router';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfButtonComponent,
  AfLoadingButtonComponent,
  AfStatusBadgeComponent,
} from '@shared/components';
import { ToastService } from '@core/services/toast.service';
import {
  type AiAgent,
  type AiAgentPayload,
  type AiAgentVoiceConfig,
  type AiVoiceResponseMode,
} from '@ai/models/ai.model';
import { AiAgentService } from '@ai/services/ai-agent.service';
import { AgentOverviewTabComponent } from './tabs/overview-tab';
import { AgentFilesTabComponent } from './tabs/files-tab';
import { AgentToolsTabComponent } from './tabs/tools-tab';
import { AgentSkillsTabComponent } from './tabs/skills-tab';
import { AgentChannelsTabComponent } from './tabs/channels-tab';
import { AgentTriggersTabComponent } from './tabs/triggers-tab';
import { AgentVoiceTabComponent } from './tabs/voice-tab';

/**
 * Tab definition for the workspace.
 */
interface WorkspaceTab {
  key: WorkspaceTabKey;
  label: string;
  icon: string;
  description: string;
}

type WorkspaceTabKey =
  | 'overview'
  | 'files'
  | 'tools'
  | 'skills'
  | 'channels'
  | 'triggers'
  | 'voice';

/**
 * Agent Configuration Workspace — sidebar layout for full agent management.
 */
@Component({
  selector: 'app-agent-workspace',
  standalone: true,
  imports: [
    LucideAngularModule,
    AfButtonComponent,
    AfLoadingButtonComponent,
    AfStatusBadgeComponent,
    AgentOverviewTabComponent,
    AgentFilesTabComponent,
    AgentToolsTabComponent,
    AgentSkillsTabComponent,
    AgentChannelsTabComponent,
    AgentTriggersTabComponent,
    AgentVoiceTabComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './agent-workspace.html',
})
export class AgentWorkspaceComponent implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly agentService = inject(AiAgentService);
  private readonly toast = inject(ToastService);
  private readonly destroyRef = inject(DestroyRef);

  readonly agentId = signal<string | null>(null);
  readonly agent = signal<AiAgent | null>(null);
  readonly loading = signal(false);
  readonly error = signal<string | null>(null);
  readonly activeTab = signal<WorkspaceTabKey>('overview');
  readonly saving = signal(false);

  readonly overviewTab = viewChild(AgentOverviewTabComponent);
  readonly channelsTab = viewChild(AgentChannelsTabComponent);
  readonly voiceTab = viewChild(AgentVoiceTabComponent);
  readonly toolsTab = viewChild(AgentToolsTabComponent);

  readonly pageTitle = computed(() => this.agent()?.name ?? 'Agente');

  readonly tabs: WorkspaceTab[] = [
    {
      key: 'overview',
      label: 'Geral',
      icon: 'settings',
      description: 'Nome, modelo e comportamento',
    },
    {
      key: 'files',
      label: 'Arquivos',
      icon: 'file-text',
      description: 'Personalidade e identidade',
    },
    { key: 'tools', label: 'Ferramentas', icon: 'wrench', description: 'Ações disponíveis' },
    { key: 'skills', label: 'Skills', icon: 'brain', description: 'Capacidades do agente' },
    {
      key: 'channels',
      label: 'Canais',
      icon: 'message-circle',
      description: 'WhatsApp, Webchat...',
    },
    { key: 'triggers', label: 'Triggers', icon: 'zap', description: 'Cron, webhooks, eventos' },
    { key: 'voice', label: 'Voz', icon: 'mic', description: 'STT e TTS configuração' },
  ];

  private readonly validTabs = new Set<WorkspaceTabKey>([
    'overview',
    'files',
    'tools',
    'skills',
    'channels',
    'triggers',
    'voice',
  ]);

  ngOnInit(): void {
    const id = this.route.snapshot.paramMap.get('id');
    if (id === null || id === '') {
      void this.router.navigate(['/ai/agents']);
      return;
    }

    const tabQuery = this.route.snapshot.queryParamMap.get('tab');
    if (tabQuery !== null && tabQuery !== '' && this.isValidTab(tabQuery)) {
      this.activeTab.set(tabQuery);
    }

    this.agentId.set(id);
    this.loadAgent(id);
  }

  /**
   * Global save: gathers form data from all active tabs and saves concurrently.
   */
  async saveAll(): Promise<void> {
    const id = this.agentId();
    if (!id) return;

    this.saving.set(true);
    const promises: Promise<unknown>[] = [];
    const payload: Partial<AiAgentPayload> = {};

    // 1. Overview Tab
    const overview = this.overviewTab();
    if (overview) {
      if (overview.form.invalid) {
        this.toast.error('Preencha os campos obrigatórios na aba Geral');
        this.activeTab.set('overview');
        this.saving.set(false);
        return;
      }
      Object.assign(payload, overview.form.value);
    }

    // 2. Channels Tab
    const channels = this.channelsTab();
    if (channels) {
      payload.channels = channels.enabledChannels();
    }

    // 3. Update core Agent payload
    if (Object.keys(payload).length > 0) {
      promises.push(lastValueFrom(this.agentService.update(id, payload)));
    }

    // 4. Voice Tab
    const voice = this.voiceTab();
    if (voice && voice.voiceForm.dirty) {
      if (voice.voiceForm.invalid) {
        this.toast.error('Preencha os campos obrigatórios na aba Voz');
        this.activeTab.set('voice');
        this.saving.set(false);
        return;
      }
      const voiceVal = voice.voiceForm.value;
      const config: AiAgentVoiceConfig = {
        voice_response_mode: voiceVal.voice_response_mode as AiVoiceResponseMode,
        stt_model: voiceVal.stt_model ?? 'whisper-1',
        stt_language: voiceVal.stt_language ?? 'pt-BR',
        tts_model: voiceVal.tts_model ?? 'tts-1',
        tts_voice: voiceVal.tts_voice ?? 'alloy',
        tts_speed: voiceVal.tts_speed ?? 1.0,
      };
      promises.push(lastValueFrom(this.agentService.updateVoiceConfig(id, config)));
    }

    // 5. Tools Tab
    const tools = this.toolsTab();
    if (tools && tools.linkedToolNames().length >= 0) {
      promises.push(lastValueFrom(this.agentService.updateAgentTools(id, tools.linkedToolNames())));
    }

    try {
      await Promise.all(promises);
      this.toast.success('Alterações salvas com sucesso.');
      if (tools) {
        tools.initialToolNames.set([...tools.linkedToolNames()]);
      }
      if (voice) {
        voice.voiceForm.markAsPristine();
      }
      this.loadAgent(id); // Reload to reflect fresh payload state
    } catch (err) {
      this.toast.error('Ocorreu um erro ao salvar alterações.');
    } finally {
      this.saving.set(false);
    }
  }

  /**
   * Navigate to simulator with this agent pre-selected.
   */
  openSimulator(): void {
    void this.router.navigate(['/ai/simulator'], {
      queryParams: { agent_id: this.agentId() },
    });
  }

  /**
   * Navigate back to agent list.
   */
  goBack(): void {
    void this.router.navigate(['/ai/agents']);
  }

  private loadAgent(id: string): void {
    this.loading.set(true);
    this.error.set(null);

    this.agentService
      .get(id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (agent) => {
          this.agent.set(agent);
          this.loading.set(false);
        },
        error: () => {
          this.error.set('Erro ao carregar agente.');
          this.loading.set(false);
        },
      });
  }

  private isValidTab(value: string): value is WorkspaceTabKey {
    return this.validTabs.has(value as WorkspaceTabKey);
  }
}
