import { type DestroyRef, Injectable, type Signal, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { switchMap } from 'rxjs/operators';
import { toast } from 'ngx-sonner';
import { CalledMessageService } from 'src/app/core/services/called-message.service';

/**
 * Gerencia o pipeline de gravação de áudio → upload → envio como mensagem.
 *
 * Extraído do host `Chat` (FEAT-049). Substitui a assinatura aninhada anterior
 * por `switchMap` para evitar vazamento de Observable interno.
 */
@Injectable({ providedIn: 'root' })
export class ChatRecordingDispatcher {
  private readonly calledMessageService = inject(CalledMessageService);

  private readonly _isSending = signal(false);
  readonly isSending: Signal<boolean> = this._isSending.asReadonly();

  /**
   * Faz upload do blob gravado e envia como mensagem de áudio.
   * A assinatura é vinculada ao DestroyRef do chamador.
   *
   * @param blob - Blob do áudio gravado.
   * @param ticketId - ID do ticket de destino.
   * @param destroyRef - Referência de destruição do componente chamador.
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
