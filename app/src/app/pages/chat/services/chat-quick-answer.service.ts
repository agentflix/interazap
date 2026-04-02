import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { type Observable, map } from 'rxjs';
import { environment } from '@env/environment';
import { type ChatQuickAnswer } from '../models/chat-quick-answer.model';

interface ChatQuickAnswerListMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

type ChatQuickAnswerListResponse =
  | ChatQuickAnswer[]
  | { data: ChatQuickAnswer[]; meta?: ChatQuickAnswerListMeta }
  | { data: { data: ChatQuickAnswer[]; meta?: ChatQuickAnswerListMeta } };

/**
 * Serviço de leitura de respostas rápidas para o chat (autocomplete).
 *
 * Carrega todas as respostas rápidas ativas do tenant e retorna
 * uma lista normalizada para uso no frontend.
 */
@Injectable({
  providedIn: 'root',
})
export class ChatQuickAnswerService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/chat/quick-answers`;

  /**
   * Carrega todas as respostas rápidas ativas do tenant.
   */
  list(): Observable<ChatQuickAnswer[]> {
    const params = new HttpParams()
      .set('is_active', 'true')
      .set('per_page', '200')
      .set('sort_by', 'shortcut')
      .set('sort_dir', 'asc');

    return this.http.get<ChatQuickAnswerListResponse>(this.baseUrl, { params }).pipe(
      map((resp) => this.normalizeList(resp)),
      map((items) =>
        items
          .filter((item) => item.is_active && item.shortcut?.trim())
          .sort((a, b) => a.shortcut.localeCompare(b.shortcut)),
      ),
    );
  }

  private normalizeList(resp: ChatQuickAnswerListResponse): ChatQuickAnswer[] {
    if (Array.isArray(resp)) {
      return resp;
    }

    if ('data' in resp && Array.isArray(resp.data)) {
      return resp.data;
    }

    if (
      'data' in resp &&
      resp.data &&
      typeof resp.data === 'object' &&
      'data' in resp.data &&
      Array.isArray(resp.data.data)
    ) {
      return resp.data.data;
    }

    return [];
  }
}
