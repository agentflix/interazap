import { describe, it, expect, beforeEach, vi } from 'vitest';
import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { of, throwError } from 'rxjs';
import MediaTranscriptionSettingsPage from './media-transcription';
import {
  MediaTranscriptionService,
  type MediaTranscriptionSettings,
} from '../../../core/services/media-transcription.service';
import { ToastService } from '../../../core/services/toast.service';

function buildSettingsResponse(): { success: boolean; data: MediaTranscriptionSettings } {
  return {
    success: true,
    data: {
      media_transcription_audio_enabled: true,
      media_transcription_image_enabled: false,
      media_transcription_video_enabled: true,
      media_transcription_audio_max_minutes: 5,
      media_transcription_image_max_per_message: 3,
      media_transcription_video_max_seconds: 30,
    },
  };
}

function buildServiceMock(): {
  show: ReturnType<typeof vi.fn>;
  update: ReturnType<typeof vi.fn>;
} {
  return {
    show: vi.fn().mockReturnValue(of(buildSettingsResponse())),
    update: vi.fn().mockReturnValue(of(buildSettingsResponse())),
  };
}

describe('MediaTranscriptionSettingsPage', () => {
  let fixture: ComponentFixture<MediaTranscriptionSettingsPage>;
  let component: MediaTranscriptionSettingsPage;
  let serviceMock: ReturnType<typeof buildServiceMock>;
  const toastMock = { success: vi.fn(), error: vi.fn() };

  beforeEach(async () => {
    serviceMock = buildServiceMock();

    await TestBed.configureTestingModule({
      imports: [MediaTranscriptionSettingsPage],
      providers: [
        { provide: MediaTranscriptionService, useValue: serviceMock },
        { provide: ToastService, useValue: toastMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(MediaTranscriptionSettingsPage);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('carrega configurações no init', () => {
    expect(serviceMock.show).toHaveBeenCalled();
    expect(component.loading()).toBe(false);
    expect(component.form.controls.audioEnabled.value).toBe(true);
    expect(component.form.controls.imageEnabled.value).toBe(false);
    expect(component.form.controls.videoEnabled.value).toBe(true);
    expect(component.form.controls.audioMaxMinutes.value).toBe(5);
  });

  it('entra em estado de erro quando carregamento falha', () => {
    serviceMock.show.mockReturnValueOnce(throwError(() => new Error('fail')));

    const view = component as unknown as { load: () => void };
    view.load();
    fixture.detectChanges();

    expect(component.error()).toBeTruthy();
    expect(component.loading()).toBe(false);
  });

  it('salva configurações e exibe toast de sucesso', () => {
    const view = component as unknown as { save: () => void };
    view.save();
    fixture.detectChanges();

    expect(serviceMock.update).toHaveBeenCalledWith({
      media_transcription_audio_enabled: true,
      media_transcription_image_enabled: false,
      media_transcription_video_enabled: true,
      media_transcription_audio_max_minutes: 5,
      media_transcription_image_max_per_message: 3,
      media_transcription_video_max_seconds: 30,
    });
    expect(toastMock.success).toHaveBeenCalled();
    expect(component.saving()).toBe(false);
  });

  it('exibe toast de erro quando save falha', () => {
    serviceMock.update.mockReturnValueOnce(throwError(() => new Error('fail')));

    const view = component as unknown as { save: () => void };
    view.save();
    fixture.detectChanges();

    expect(toastMock.error).toHaveBeenCalled();
    expect(component.saving()).toBe(false);
  });

  it('não salva quando form é inválido', () => {
    component.form.controls.audioMaxMinutes.setValue(0);
    component.form.controls.audioMaxMinutes.markAsTouched();

    const view = component as unknown as { save: () => void };
    view.save();

    expect(serviceMock.update).not.toHaveBeenCalled();
  });

  it('valida limites máximos dos campos', () => {
    component.form.controls.audioMaxMinutes.setValue(26); // max 25
    expect(component.form.controls.audioMaxMinutes.valid).toBe(false);

    component.form.controls.imageMaxPerMessage.setValue(11); // max 10
    expect(component.form.controls.imageMaxPerMessage.valid).toBe(false);

    component.form.controls.videoMaxSeconds.setValue(121); // max 120
    expect(component.form.controls.videoMaxSeconds.valid).toBe(false);
  });

  it('valida limites mínimos dos campos', () => {
    component.form.controls.audioMaxMinutes.setValue(0); // min 1
    expect(component.form.controls.audioMaxMinutes.valid).toBe(false);

    component.form.controls.imageMaxPerMessage.setValue(0); // min 1
    expect(component.form.controls.imageMaxPerMessage.valid).toBe(false);

    component.form.controls.videoMaxSeconds.setValue(4); // min 5
    expect(component.form.controls.videoMaxSeconds.valid).toBe(false);
  });
});
