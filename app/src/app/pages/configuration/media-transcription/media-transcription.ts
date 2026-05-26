import { ChangeDetectionStrategy, Component, DestroyRef, inject, signal } from '@angular/core';
import { FormControl, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfButtonComponent,
  AfEmptyStateComponent,
  AfLoadingButtonComponent,
  AfPageTitleComponent,
  AfSwitchInputComponent,
  AfTextInputComponent,
} from '@shared/components';
import { ToastService } from '@core/services/toast.service';
import {
  type MediaTranscriptionSettings,
  MediaTranscriptionService,
} from '@core/services/media-transcription.service';

/**
 * Página de configuração de transcrição de mídia.
 *
 * @description Permite habilitar/desabilitar transcrição automática de áudio,
 * imagem e vídeo, além de configurar limites de processamento por tipo.
 */
@Component({
  selector: 'app-media-transcription-settings',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    LucideAngularModule,
    AfPageTitleComponent,
    AfEmptyStateComponent,
    AfButtonComponent,
    AfLoadingButtonComponent,
    AfSwitchInputComponent,
    AfTextInputComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './media-transcription.html',
})
export class MediaTranscriptionSettingsPage {
  private readonly service = inject(MediaTranscriptionService);
  private readonly toast = inject(ToastService);
  private readonly destroyRef = inject(DestroyRef);

  readonly loading = signal(true);
  readonly saving = signal(false);
  readonly error = signal<string | null>(null);

  readonly form = new FormGroup({
    audioEnabled: new FormControl(false, { nonNullable: true }),
    imageEnabled: new FormControl(false, { nonNullable: true }),
    videoEnabled: new FormControl(false, { nonNullable: true }),
    audioMaxMinutes: new FormControl(5, {
      nonNullable: true,
      validators: [Validators.required, Validators.min(1), Validators.max(25)],
    }),
    imageMaxPerMessage: new FormControl(3, {
      nonNullable: true,
      validators: [Validators.required, Validators.min(1), Validators.max(10)],
    }),
    videoMaxSeconds: new FormControl(30, {
      nonNullable: true,
      validators: [Validators.required, Validators.min(5), Validators.max(120)],
    }),
  });

  constructor() {
    this.load();
  }

  /** Carrega as configurações atuais de transcrição de mídia da API. */
  protected load(): void {
    this.loading.set(true);
    this.error.set(null);

    this.service
      .show()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.patchForm(response.data);
          this.loading.set(false);
        },
        error: () => {
          this.error.set('Não foi possível carregar as configurações de transcrição.');
          this.loading.set(false);
        },
      });
  }

  /** Salva as configurações de transcrição de mídia via API após validar o formulário. */
  protected save(): void {
    if (this.form.invalid || this.saving()) {
      return;
    }

    this.saving.set(true);

    const payload: MediaTranscriptionSettings = {
      media_transcription_audio_enabled: this.form.controls.audioEnabled.value,
      media_transcription_image_enabled: this.form.controls.imageEnabled.value,
      media_transcription_video_enabled: this.form.controls.videoEnabled.value,
      media_transcription_audio_max_minutes: this.form.controls.audioMaxMinutes.value,
      media_transcription_image_max_per_message: this.form.controls.imageMaxPerMessage.value,
      media_transcription_video_max_seconds: this.form.controls.videoMaxSeconds.value,
    };

    this.service
      .update(payload)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.patchForm(response.data);
          this.toast.success('Configurações de transcrição salvas com sucesso.');
          this.saving.set(false);
        },
        error: () => {
          this.toast.error('Erro ao salvar configurações de transcrição.');
          this.saving.set(false);
        },
      });
  }

  /**
   * Preenche os controles do formulário com os dados retornados pela API.
   * @param data Configurações retornadas pelo backend
   */
  private patchForm(data: MediaTranscriptionSettings): void {
    this.form.patchValue({
      audioEnabled: data.media_transcription_audio_enabled,
      imageEnabled: data.media_transcription_image_enabled,
      videoEnabled: data.media_transcription_video_enabled,
      audioMaxMinutes: data.media_transcription_audio_max_minutes,
      imageMaxPerMessage: data.media_transcription_image_max_per_message,
      videoMaxSeconds: data.media_transcription_video_max_seconds,
    });
  }
}

export default MediaTranscriptionSettingsPage;
