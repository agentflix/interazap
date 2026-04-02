import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';

export interface PresencePayload {
  presence: 'composing' | 'recording' | 'paused';
  delay?: number;
}

export interface PresenceResponse {
  success: boolean;
  message: string;
  data?: unknown;
}

/**
 * Service for sending presence indicators (typing, recording) to chat contacts.
 *
 * @remarks
 * Sends presence signals to the backend which relays them to the contact
 * via WhatsApp or other messaging channels.
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
