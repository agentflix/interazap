import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  inject,
  input,
  signal,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfSelectInputComponent,
  AfTextInputComponent,
  type AfSelectOption,
} from '@shared/components';
import { ToastService } from '@core/services/toast.service';
import { type AiAgentVoiceConfig, type AiVoiceResponseMode } from '@ai/models/ai.model';
import { AiAgentService } from '@ai/services/ai-agent.service';

/**
 * Voice tab — configure STT/TTS and voice response mode.
 */
@Component({
  selector: 'app-agent-voice-tab',
  standalone: true,
  imports: [ReactiveFormsModule, LucideAngularModule, AfSelectInputComponent, AfTextInputComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './voice-tab.html',
})
export class AgentVoiceTabComponent {
  private readonly agentService = inject(AiAgentService);
  private readonly toast = inject(ToastService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly fb = inject(FormBuilder);

  readonly agentId = input.required<string>();
  readonly loading = signal(false);
  readonly error = signal<string | null>(null);
  readonly saving = signal(false);

  readonly voiceForm = this.fb.group({
    voice_response_mode: ['text' as AiVoiceResponseMode],
    stt_model: ['whisper-1'],
    stt_language: ['pt-BR'],
    tts_model: ['tts-1'],
    tts_voice: ['alloy'],
    tts_speed: [1.0, [Validators.min(0.25), Validators.max(4.0)]],
  });

  readonly responseModes: {
    value: AiVoiceResponseMode;
    label: string;
    description: string;
    icon: string;
  }[] = [
    {
      value: 'text',
      label: 'Somente Texto',
      description: 'Responde apenas com texto',
      icon: 'type',
    },
    {
      value: 'audio',
      label: 'Somente Áudio',
      description: 'Responde apenas com áudio',
      icon: 'volume-2',
    },
    {
      value: 'mixed',
      label: 'Texto + Áudio',
      description: 'Responde com texto e áudio',
      icon: 'audio-lines',
    },
  ];

  readonly sttModelOptions: AfSelectOption[] = [
    { value: 'whisper-1', label: 'Whisper v1' },
    { value: 'whisper-large-v3', label: 'Whisper Large v3' },
  ];

  readonly sttLanguageOptions: AfSelectOption[] = [
    { value: 'pt-BR', label: 'Português (BR)' },
    { value: 'en-US', label: 'English (US)' },
    { value: 'es-ES', label: 'Español' },
  ];

  readonly ttsModelOptions: AfSelectOption[] = [
    { value: 'tts-1', label: 'TTS-1 (padrão)' },
    { value: 'tts-1-hd', label: 'TTS-1 HD' },
  ];

  readonly ttsVoiceOptions: AfSelectOption[] = [
    { value: 'alloy', label: 'Alloy' },
    { value: 'echo', label: 'Echo' },
    { value: 'fable', label: 'Fable' },
    { value: 'onyx', label: 'Onyx' },
    { value: 'nova', label: 'Nova' },
    { value: 'shimmer', label: 'Shimmer' },
  ];

  constructor() {
    setTimeout(() => this.loadConfig(), 0);
  }

  // configuration is now saved globally by the workspace

  private loadConfig(): void {
    const id = this.agentId();
    if (!id) return;

    this.loading.set(true);
    this.error.set(null);

    this.agentService
      .getVoiceConfig(id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (config) => {
          if (config !== null && config !== undefined) {
            this.voiceForm.patchValue({
              voice_response_mode: this.normalizeVoiceResponseMode(config.voice_response_mode),
              stt_model: config.stt_model ?? 'whisper-1',
              stt_language: config.stt_language ?? 'pt-BR',
              tts_model: config.tts_model ?? 'tts-1',
              tts_voice: config.tts_voice ?? 'alloy',
              tts_speed: config.tts_speed ?? 1.0,
            });
          }
          this.loading.set(false);
        },
        error: () => {
          this.error.set('Erro ao carregar configuração de voz.');
          this.loading.set(false);
        },
      });
  }

  private normalizeVoiceResponseMode(mode: string | null | undefined): AiVoiceResponseMode {
    if (mode === 'text_only') return 'text';
    if (mode === 'audio_only') return 'audio';
    if (mode === 'both') return 'mixed';
    if (mode === 'audio' || mode === 'mixed') return mode;
    return 'text';
  }
}
