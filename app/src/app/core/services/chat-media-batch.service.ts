import { HttpEventType } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { type Observable, catchError, concat, from, mergeMap, of } from 'rxjs';
import {
  type CalledMessage,
  type CalledMessageMediaResponse,
  CalledMessageService,
} from 'src/app/core/services/called-message.service';
import { type MediaPreviewItem } from 'src/app/shared/models/media-preview.model';
import type { MediaBatchEvent } from '@core/models/chat-media-batch.model';
export type { MediaBatchEvent, MediaBatchPhase } from '@core/models/chat-media-batch.model';



@Injectable({ providedIn: 'root' })
export class ChatMediaBatchService {
  private readonly messageService = inject(CalledMessageService);

  /**
   * Dispara o upload e envio de multiplos itens de midia em lote.
   * Controla a concorrencia de uploads e emite eventos de progresso por item.
   *
   * @param calledId - Identificador da chamada/sessao de chat
   * @param items - Array de itens de midia (arquivo + metadados)
   * @param concurrency - Numero maximo de uploads simultaneos (default: 2)
   * @returns Observable emitindo MediaBatchEvent para cada fase do processo:
   *   'uploading' (progresso), 'uploaded', 'sent' ou 'failed'
   */
  dispatchBatch(
    calledId: string,
    items: MediaPreviewItem[],
    concurrency = 2,
  ): Observable<MediaBatchEvent> {
    return from(items).pipe(mergeMap((item) => this.uploadAndSend(calledId, item), concurrency));
  }

  private uploadAndSend(calledId: string, item: MediaPreviewItem): Observable<MediaBatchEvent> {
    return this.messageService.uploadMediaWithProgress(item.file).pipe(
      mergeMap((event) => {
        if (event.type === HttpEventType.UploadProgress) {
          const loaded = event.loaded ?? 0;
          const total = event.total ?? item.file.size ?? 0;
          return of({ id: item.id, phase: 'uploading' as const, loaded, total });
        }

        if (event.type === HttpEventType.Response) {
          const payload = event.body as CalledMessageMediaResponse | null;
          const url = payload?.data?.url;
          if (!url) {
            return of({ id: item.id, phase: 'failed' as const });
          }

          return concat(
            of({
              id: item.id,
              phase: 'uploaded' as const,
              loaded: item.file.size ?? 0,
              total: item.file.size ?? 0,
            }),
            this.messageService
              .send(
                calledId,
                item.caption ?? '',
                item.type,
                {},
                {
                  file_url: url,
                  file_name: payload?.data?.file_name ?? item.file.name,
                  mime_type: payload?.data?.mime_type ?? item.file.type,
                  file_size: payload?.data?.file_size ?? payload?.data?.size ?? item.file.size,
                },
              )
              .pipe(
                mergeMap((response) =>
                  of({ id: item.id, phase: 'sent' as const, message: response.data }),
                ),
              ),
          );
        }

        return of({ id: item.id, phase: 'uploading' as const });
      }),
      catchError(() => of({ id: item.id, phase: 'failed' as const })),
    );
  }
}
