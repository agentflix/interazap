import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { ReactiveFormsModule, FormControl } from '@angular/forms';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { provideRouter } from '@angular/router';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { ChatConversationComponent } from './chat-conversation-component';
import { type TemplateSelectedEvent } from '@shared/components/template-selector/template-selector';

describe('ChatConversationComponent', () => {
  let component: ChatConversationComponent;
  let fixture: ComponentFixture<ChatConversationComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ChatConversationComponent, ReactiveFormsModule],
      providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([])],
    }).compileComponents();

    fixture = TestBed.createComponent(ChatConversationComponent);
    component = fixture.componentInstance;

    // Inputs obrigatórios
    fixture.componentRef.setInput('activeTab', 'chat');
    fixture.componentRef.setInput('selectedTicketId', 'test-ticket-1');
    fixture.componentRef.setInput('selectedTicketInitials', 'AB');
    fixture.componentRef.setInput('isSelectedTicketStarted', true);
    fixture.componentRef.setInput('isStartingTicket', false);
    fixture.componentRef.setInput('isTransferLoading', false);
    fixture.componentRef.setInput('isClosingTicket', false);
    fixture.componentRef.setInput('isRecording', false);
    fixture.componentRef.setInput('isRecordingPaused', false);
    fixture.componentRef.setInput('recordingDuration', 0);
    fixture.componentRef.setInput('isSendingAudio', false);
    fixture.componentRef.setInput('isMobile', false);
    fixture.componentRef.setInput('isAttachmentMenuOpen', false);
    fixture.componentRef.setInput('pendingQueueCount', 0);
    fixture.componentRef.setInput('isQueueFlushing', false);
    fixture.componentRef.setInput('messageControl', new FormControl<string>('', { nonNullable: true }));
    fixture.componentRef.setInput('isSendingMessage', false);

    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('renders textarea when composerMode is free', () => {
    fixture.componentRef.setInput('composerMode', 'free');
    fixture.detectChanges();
    const textarea = fixture.nativeElement.querySelector('textarea');
    expect(textarea).toBeTruthy();
  });

  it('renders textarea + template button when composerMode is mixed', () => {
    fixture.componentRef.setInput('composerMode', 'mixed');
    fixture.componentRef.setInput('chatInstanceId', 'ci-1');
    fixture.detectChanges();
    const textarea = fixture.nativeElement.querySelector('textarea');
    expect(textarea).toBeTruthy();
  });

  it('renders banner + CTA when composerMode is template-only', () => {
    fixture.componentRef.setInput('composerMode', 'template-only');
    fixture.detectChanges();
    const banner = fixture.nativeElement.querySelector('af-banner');
    expect(banner).toBeTruthy();
    const textarea = fixture.nativeElement.querySelector('textarea');
    expect(textarea).toBeFalsy();
  });

  it('emits templateSelected when template is chosen', () => {
    const emitted: TemplateSelectedEvent[] = [];
    component.templateSelected.subscribe((e) => emitted.push(e));

    const event: TemplateSelectedEvent = {
      templateName: 'boas_vindas',
      parameters: { param_0: 'João' },
    };
    component.templateSelected.emit(event);
    expect(emitted).toContainEqual(event);
  });

  it('emits clearTemplateSelected when clearing template', () => {
    let cleared = false;
    component.clearTemplateSelected.subscribe(() => {
      cleared = true;
    });
    component.clearTemplateSelected.emit();
    expect(cleared).toBe(true);
  });
});
