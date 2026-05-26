import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';
import type { PresencePayload, PresenceResponse } from '@core/models/chat-presence.model';
export type { PresencePayload, PresenceResponse } from '@core/models/chat-presence.model';



/**
 * Envia indicadores de presença (digitando, gravando) ao backend para relay ao contato do ticket.
 *
 * @remarks
 * O backend repassa os sinais de presença ao contato via WhatsApp ou
 * outros canais de mensageria configurados.
 */
@Injectable({ providedIn: 'root' })
export class ChatPresenceService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/chat/tickets`;

  /**
   * Envia indicador de presença (digitando/gravando) para o contato do ticket.
   */
  send(ticketId: string, payload: PresencePayload): Observable<PresenceResponse> {
    return this.http.post<PresenceResponse>(`${this.baseUrl}/${ticketId}/presence`, payload);
  }

  /**
   * Inicia indicador de digitação.
   */
  startTyping(ticketId: string, durationMs = 30000): Observable<PresenceResponse> {
    return this.send(ticketId, { presence: 'composing', delay: durationMs });
  }

  /**
   * Inicia indicador de gravação de áudio.
   */
  startRecording(ticketId: string, durationMs = 60000): Observable<PresenceResponse> {
    return this.send(ticketId, { presence: 'recording', delay: durationMs });
  }

  /**
   * Cancela indicador de presença.
   */
  stop(ticketId: string): Observable<PresenceResponse> {
    return this.send(ticketId, { presence: 'paused' });
  }
}
