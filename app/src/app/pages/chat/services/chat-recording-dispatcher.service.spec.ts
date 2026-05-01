import type { DestroyRef } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { of, throwError } from 'rxjs';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { CalledMessageService } from 'src/app/core/services/called-message.service';
import { ChatRecordingDispatcher } from './chat-recording-dispatcher.service';

vi.mock('ngx-sonner', () => ({
  toast: { success: vi.fn(), error: vi.fn() },
}));

class CalledMessageServiceStub {
  uploadMedia = vi.fn().mockReturnValue(
    of({
      data: { url: 'https://x/y', file_name: 'audio.webm', mime_type: 'audio/webm', size: 1234 },
    }),
  );
  send = vi.fn().mockReturnValue(of({ data: { id: 'm1' } }));
}

describe('ChatRecordingDispatcher', () => {
  let service: ChatRecordingDispatcher;
  let api: CalledMessageServiceStub;
  let destroyRef: DestroyRef;

  beforeEach(() => {
    api = new CalledMessageServiceStub();
    TestBed.configureTestingModule({
      providers: [
        ChatRecordingDispatcher,
        { provide: CalledMessageService, useValue: api },
      ],
    });
    service = TestBed.inject(ChatRecordingDispatcher);
    destroyRef = { onDestroy: vi.fn() } as unknown as DestroyRef;
  });

  it('uploads then sends in sequence (switchMap pipeline)', () => {
    const blob = new Blob(['x'], { type: 'audio/webm' });
    service.dispatch(blob, 't1', destroyRef);
    expect(api.uploadMedia).toHaveBeenCalled();
    expect(api.send).toHaveBeenCalledWith('t1', '', 'audio', undefined, {
      file_url: 'https://x/y',
      file_name: 'audio.webm',
      mime_type: 'audio/webm',
      file_size: 1234,
    });
    expect(service.isSending()).toBe(false);
  });

  it('handles upload failure', () => {
    api.uploadMedia = vi.fn().mockReturnValue(throwError(() => new Error('boom')));
    service.dispatch(new Blob(['x']), 't1', destroyRef);
    expect(api.send).not.toHaveBeenCalled();
    expect(service.isSending()).toBe(false);
  });

  it('skips when ticketId is missing', () => {
    service.dispatch(new Blob(['x']), '', destroyRef);
    expect(api.uploadMedia).not.toHaveBeenCalled();
  });
});
