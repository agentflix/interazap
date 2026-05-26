import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';
import type { MediaTranscriptionSettings, MediaTranscriptionSettingsResponse } from '@shared/models/media-preview.model';
export type { MediaTranscriptionSettings, MediaTranscriptionSettingsResponse } from '@shared/models/media-preview.model';


/**
 * Gerencia configurações de transcrição de mídia do tenant (áudio, imagem, vídeo).
 */
@Injectable({
  providedIn: 'root',
})
export class MediaTranscriptionService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = `${environment.apiUrl}/media-transcription`;

  /**
   * Retorna as configurações atuais de transcrição de mídia do tenant.
   * Inclui settings para áudio, imagem e vídeo.
   * @returns Observable com configurações de transcrição
   */
  show(): Observable<MediaTranscriptionSettingsResponse> {
    return this.http.get<MediaTranscriptionSettingsResponse>(this.apiUrl);
  }

  /**
   * Atualiza as configurações de transcrição de mídia do tenant.
   * @param data Novas configurações de transcrição (áudio, imagem, vídeo)
   * @returns Observable com configurações atualizadas
   */
  update(data: MediaTranscriptionSettings): Observable<MediaTranscriptionSettingsResponse> {
    return this.http.put<MediaTranscriptionSettingsResponse>(this.apiUrl, data);
  }
}
