import { TestBed, type ComponentFixture } from '@angular/core/testing';
import { describe, expect, it, beforeEach, vi } from 'vitest';
import { of } from 'rxjs';
import { AgentVoiceTabComponent } from './voice-tab';
import { AiAgentService } from '@ai/services/ai-agent.service';
import { ToastService } from '@core/services/toast.service';

describe('AgentVoiceTabComponent', () => {
  let component: AgentVoiceTabComponent;
  let fixture: ComponentFixture<AgentVoiceTabComponent>;

  const aiAgentServiceMock = {
    getVoiceConfig: vi.fn().mockReturnValue(
      of({
        voice_response_mode: 'audio',
        stt_model: 'whisper-1',
        stt_language: 'pt-BR',
        tts_model: 'tts-1',
        tts_voice: 'alloy',
        tts_speed: 1,
      }),
    ),
  };

  const toastServiceMock = {
    success: vi.fn(),
    error: vi.fn(),
  };

  beforeEach(async () => {
    vi.clearAllMocks();

    await TestBed.configureTestingModule({
      imports: [AgentVoiceTabComponent],
      providers: [
        { provide: AiAgentService, useValue: aiAgentServiceMock },
        { provide: ToastService, useValue: toastServiceMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(AgentVoiceTabComponent);
    component = fixture.componentInstance;
    fixture.componentRef.setInput('agentId', 'uuid');
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('should reflect canonical API voice mode in selected option', () => {
    (component as unknown as { loadConfig: () => void }).loadConfig();
    expect(component.voiceForm.controls.voice_response_mode.value).toBe('audio');
  });

  it('should normalize legacy API voice mode values', () => {
    aiAgentServiceMock.getVoiceConfig.mockReturnValueOnce(
      of({
        voice_response_mode: 'both',
        stt_model: 'whisper-1',
        stt_language: 'pt-BR',
        tts_model: 'tts-1',
        tts_voice: 'alloy',
        tts_speed: 1,
      }),
    );

    (component as unknown as { loadConfig: () => void }).loadConfig();

    expect(component.voiceForm.controls.voice_response_mode.value).toBe('mixed');
  });
});
