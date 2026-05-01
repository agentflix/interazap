import { type DestroyRef, Injectable, type Signal, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { switchMap } from 'rxjs/operators';
import { toast } from 'ngx-sonner';
import { CalledMessageService } from 'src/app/core/services/called-message.service';

/**
 * Handles the audio-recording → upload → send pipeline.
 *
 * Extracted from `Chat` host (FEAT-049). Replaces the previous nested
 * subscription with a `switchMap` to avoid leaking the inner observable.
 */
@Injectable({ providedIn: 'root' })
export class ChatRecordingDispatcher {
  private readonly calledMessageService = inject(CalledMessageService);

  private readonly _isSending = signal(false);
  readonly isSending: Signal<boolean> = this._isSending.asReadonly();

  /**
   * Uploads the recorded blob and sends it as an audio message.
   * Subscription is bound to the caller's DestroyRef.
   */
  dispatch(blob: Blob, ticketId: string | number, destroyRef: DestroyRef): void {
    if (!ticketId) return;

    const audioFile = new File([blob], 'audio-message.webm', { type: 'audio/webm' });
    this._isSending.set(true);

    this.calledMessageService
      .uploadMedia(audioFile)
      .pipe(
        switchMap((res) =>
          this.calledMessageService.send(String(ticketId), '', 'audio', undefined, {
            file_url: res.data.url,
            file_name: res.data.file_name,
            mime_type: res.data.mime_type,
            file_size: res.data.size,
          }),
        ),
        takeUntilDestroyed(destroyRef),
      )
      .subscribe({
        next: () => {
          this._isSending.set(false);
        },
        error: () => {
          this._isSending.set(false);
          toast.error('Erro ao enviar áudio.');
        },
      });
  }
}
