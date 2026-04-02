import { Component, input, output } from '@angular/core';
import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { ActivatedRoute, convertToParamMap } from '@angular/router';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { of } from 'rxjs';
import { type CalledMessage } from 'src/app/core/services/called-message.service';
import { UserChat } from './user-chat';

@Component({
  selector: 'app-user-chat-thread',
  standalone: true,
  template: '',
})
class UserChatThreadStubComponent {
  readonly allowStartConversation = input(true);
  readonly ticketId = input<string | null | undefined>(undefined);
  readonly canLoadMessages = input(true);
  readonly readOnlyMode = input(false);

  readonly reply = output<CalledMessage>();

  readonly appendMessage = vi.fn<(message: CalledMessage) => void>();
  readonly replaceMessage = vi.fn<(tempId: string, message: CalledMessage) => void>();
}

describe('UserChat', () => {
  let fixture: ComponentFixture<UserChat>;
  let component: UserChat;

  const message = {
    id: 'message-1',
    content: 'Mensagem de teste',
    direction: 'incoming',
  } as CalledMessage;

  beforeEach(async () => {
    TestBed.configureTestingModule({
      imports: [UserChat],
      providers: [
        {
          provide: ActivatedRoute,
          useValue: {
            snapshot: { paramMap: convertToParamMap({ calledId: null }) },
            paramMap: of(convertToParamMap({ calledId: null })),
          },
        },
      ],
    });

    TestBed.overrideComponent(UserChat, {
      set: {
        imports: [UserChatThreadStubComponent],
      },
    });

    await TestBed.compileComponents();

    fixture = TestBed.createComponent(UserChat);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('should emit reply when onReply is called', () => {
    const emitSpy = vi.spyOn(component.reply, 'emit');

    component.onReply(message);

    expect(emitSpy).toHaveBeenCalledWith(message);
  });

  it('should delegate appendMessage to threadRef when present', () => {
    const appendMessageSpy = vi.fn<(value: CalledMessage) => void>();
    Object.defineProperty(component, 'threadRef', {
      value: {
        appendMessage: appendMessageSpy,
        replaceMessage: vi.fn<(tempId: string, value: CalledMessage) => void>(),
      },
    });

    component.appendMessage(message);

    expect(appendMessageSpy).toHaveBeenCalledWith(message);
  });

  it('should delegate replaceMessage to threadRef when present', () => {
    const replaceMessageSpy = vi.fn<(tempId: string, value: CalledMessage) => void>();
    Object.defineProperty(component, 'threadRef', {
      value: {
        appendMessage: vi.fn<(value: CalledMessage) => void>(),
        replaceMessage: replaceMessageSpy,
      },
    });

    component.replaceMessage('temp-1', message);

    expect(replaceMessageSpy).toHaveBeenCalledWith('temp-1', message);
  });
});
