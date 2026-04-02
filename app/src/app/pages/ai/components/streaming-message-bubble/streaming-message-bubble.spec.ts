import { TestBed, type ComponentFixture } from '@angular/core/testing';
import { importProvidersFrom } from '@angular/core';
import { LucideAngularModule, icons } from 'lucide-angular';
import { describe, expect, it, beforeEach } from 'vitest';
import { StreamingMessageBubbleComponent } from './streaming-message-bubble';

describe('StreamingMessageBubbleComponent', () => {
  let fixture: ComponentFixture<StreamingMessageBubbleComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [StreamingMessageBubbleComponent],
      providers: [importProvidersFrom(LucideAngularModule.pick(icons))],
    }).compileComponents();

    fixture = TestBed.createComponent(StreamingMessageBubbleComponent);
  });

  it('renders progressive text and typing indicator while streaming', () => {
    fixture.componentRef.setInput('text', 'Processando resposta');
    fixture.componentRef.setInput('isStreaming', true);
    fixture.componentRef.setInput('isFinal', false);
    fixture.detectChanges();

    const element = fixture.nativeElement as HTMLElement;
    expect(element.textContent).toContain('Processando resposta');
    expect(element.querySelectorAll('.animate-bounce').length).toBe(3);
  });

  it('renders audio player when audio url is provided', () => {
    fixture.componentRef.setInput('audioUrl', 'https://cdn.test/audio.mp3');
    fixture.detectChanges();

    const element = fixture.nativeElement as HTMLElement;
    expect(element.querySelector('audio')).not.toBeNull();
  });
});
