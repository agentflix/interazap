import { type HttpEvent, HttpClient, HttpParams, HttpRequest } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';

export interface CalledMessageUser {
  id: string | number;
  name: string;
}

export interface CalledMessageContactPhone {
  number?: string;
  type?: string;
}

export interface CalledMessageContactEntry {
  fullName?: string;
  phoneNumber?: string;
  organization?: string;
  email?: string;
  phones?: CalledMessageContactPhone[];
}

export interface CalledMessageContactMetadata {
  fullName?: string;
  phoneNumber?: string;
  organization?: string;
  email?: string;
  url?: string;
  contacts?: CalledMessageContactEntry[];
}

export interface CalledMessageLocationMetadata {
  latitude?: number;
  longitude?: number;
  lat?: number;
  lng?: number;
  address?: string;
  name?: string;
}

export interface CalledMessageMetadata {
  contact?: CalledMessageContactMetadata;
  location?: CalledMessageLocationMetadata;
  [key: string]: unknown;
}

export interface CalledMessageQuotedMessage {
  id?: string | number | null;
  external_id?: string | null;
  content?: string | null;
  type?: string | null;
  direction?: 'incoming' | 'outgoing' | null;
  sender_name?: string | null;
  file_name?: string | null;
  mime_type?: string | null;
  is_edited?: boolean;
  edited_at?: string | null;
}

export interface CalledMessage {
  id: string | number;
  called_id?: string | number;
  ticket_id?: string | number;
  user_id?: string | number | null;
  contact_id?: string | number | null;
  external_id?: string | null;
  content?: string | null;
  type?: string;
  direction?: 'incoming' | 'outgoing';
  status?: string | null;
  is_deleted?: boolean;
  file_url?: string | null;
  file_name?: string | null;
  mime_type?: string | null;
  file_size?: number | null;
  sent_at?: string | null;
  delivered_at?: string | null;
  read_at?: string | null;
  deleted_at?: string | null;
  deleted_by?: string | null;
  reactions?: { emoji: string; from_me: boolean; timestamp: string }[] | null;
  is_edited?: boolean;
  edited_at?: string | null;
  edit_history?: { content: string; edited_at: string }[] | null;
  media_transcription?: string | null;
  media_transcription_status?: 'pending' | 'processing' | 'completed' | 'failed' | null;
  media_transcription_provider?: string | null;
  created_at?: string | null;
  metadata?: CalledMessageMetadata | null;
  quoted_message?: CalledMessageQuotedMessage | null;
  user?: CalledMessageUser | null;
}

export interface CalledMessageListResponse {
  data: {
    messages: CalledMessage[];
    meta: PaginationMeta;
  };
}

export interface CalledMessageSendResponse {
  data: CalledMessage;
}

export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  has_more: boolean;
}

export interface CalledMessageListOptions {
  page?: number;
  limit?: number;
  since?: string;
}

export interface CalledMessageMediaResponse {
  data: {
    url: string;
    file_name?: string | null;
    mime_type?: string | null;
    file_size?: number | null;
    size?: number | null;
    metadata?: Record<string, unknown> | null;
  };
}

/**
 * Serviço para gestão de mensagens de atendimentos.
 *
 * Responsável por listar o histórico de conversas, enviar novas mensagens (texto e arquivos)
 * e gerenciar o upload de mídias para o servidor.
 *
 * @class CalledMessageService
 * @description Service para troca de mensagens e gestão de mídias de chat.
 */
@Injectable({ providedIn: 'root' })
export class CalledMessageService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/chat/tickets`;

  /**
   * Lista o histórico de mensagens de um atendimento específico com suporte a paginação.
   * @param calledId Identificador do atendimento.
   * @param page Número da página a ser carregada.
   * @returns {Observable<CalledMessageListResponse>} Stream finito com a lista de mensagens e metadados.
   */
  list(
    calledId: string,
    pageOrOptions: number | CalledMessageListOptions = 1,
  ): Observable<CalledMessageListResponse> {
    const options: CalledMessageListOptions =
      typeof pageOrOptions === 'number' ? { page: pageOrOptions } : pageOrOptions;

    let params = new HttpParams().set('page', String(options.page ?? 1));

    if (typeof options.limit === 'number' && Number.isFinite(options.limit) && options.limit > 0) {
      params = params.set('limit', String(options.limit));
    }

    if (typeof options.since === 'string' && options.since.trim() !== '') {
      params = params.set('since', options.since.trim());
    }

    return this.http.get<CalledMessageListResponse>(`${this.baseUrl}/${calledId}/messages`, {
      params,
    });
  }

  /**
   * Envia uma nova mensagem de texto ou mídia para o atendimento.
   * @param calledId Identificador do atendimento.
   * @param content Conteúdo textual da mensagem.
   * @param type Tipo da mensagem (text, image, video, audio, file).
   * @param metadata Metadados adicionais da mensagem.
   * @param fileData Dados do arquivo em anexo (caso enviado).
   * @returns {Observable<CalledMessageSendResponse>} Stream finito com os dados da mensagem enviada.
   */
  send(
    calledId: string,
    content: string,
    type = 'text',
    metadata?: Record<string, unknown>,
    fileData?: {
      file_url?: string;
      file_name?: string;
      mime_type?: string;
      file_size?: number;
    },
    clientMessageId?: string,
  ): Observable<CalledMessageSendResponse> {
    const quotedMessageId =
      metadata !== undefined && 'quoted_message_id' in metadata
        ? (metadata['quoted_message_id'] as string | number | undefined)
        : undefined;

    return this.http.post<CalledMessageSendResponse>(`${this.baseUrl}/${calledId}/messages`, {
      content,
      type,
      metadata,
      ...(quotedMessageId !== undefined ? { quoted_message_id: quotedMessageId } : {}),
      ...fileData,
      ...(clientMessageId !== undefined ? { client_message_id: clientMessageId } : {}),
    });
  }

  /**
   * Realiza o upload de um arquivo para o storage de mídias do chat.
   * @param file O arquivo (Blob/File) a ser enviado.
   * @returns {Observable<{ data: { url: string; file_name: string; mime_type: string; size: number } }>} Dados da mídia persistida.
   */
  uploadMedia(
    file: File,
  ): Observable<{ data: { url: string; file_name: string; mime_type: string; size: number } }> {
    const formData = new FormData();
    formData.append('file', file);
    return this.http.post<{
      data: { url: string; file_name: string; mime_type: string; size: number };
    }>(`${environment.apiUrl}/chat/media`, formData);
  }

  /**
   * Media upload with progress (XHR).
   * @param file File to upload.
   */
  uploadMediaWithProgress(
    file: File,
  ): Observable<
    HttpEvent<{ data: { url: string; file_name: string; mime_type: string; size: number } }>
  > {
    const formData = new FormData();
    formData.append('file', file);

    const req = new HttpRequest('POST', `${environment.apiUrl}/chat/media`, formData, {
      reportProgress: true,
    });

    return this.http.request(req);
  }

  /**
   * Recupera metadados de uma mídia associada a uma mensagem específica.
   * @param calledId Identificador do atendimento.
   * @param messageId Identificador da mensagem.
   * @returns {Observable<CalledMessageMediaResponse>} Stream finito com os dados da mídia.
   */
  getMediaUrl(calledId: string, messageId: string): Observable<CalledMessageMediaResponse> {
    return this.http.get<CalledMessageMediaResponse>(
      `${this.baseUrl}/${calledId}/messages/${messageId}/media`,
    );
  }

  /**
   * Reage a uma mensagem com um emoji.
   * @param calledId Identificador do atendimento.
   * @param messageId Identificador da mensagem.
   * @param emoji Emoji de reação (string vazia para remover).
   * @returns {Observable<{ success: boolean; message?: string }>} Resultado da operação.
   */
  react(
    calledId: string,
    messageId: string,
    emoji: string,
  ): Observable<{ success: boolean; message?: string }> {
    return this.http.post<{ success: boolean; message?: string }>(
      `${this.baseUrl}/${calledId}/messages/${messageId}/react`,
      { emoji },
    );
  }

  /**
   * Edita o conteúdo de uma mensagem já enviada.
   * @param calledId Identificador do atendimento.
   * @param messageId Identificador da mensagem.
   * @param content Novo conteúdo textual.
   * @returns {Observable<{ success: boolean; message?: string }>} Resultado da operação.
   */
  edit(
    calledId: string,
    messageId: string,
    content: string,
  ): Observable<{ success: boolean; message?: string }> {
    return this.http.post<{ success: boolean; message?: string }>(
      `${this.baseUrl}/${calledId}/messages/${messageId}/edit`,
      { content },
    );
  }

  /**
   * Exclui uma mensagem para todos os participantes.
   * @param calledId Identificador do atendimento.
   * @param messageId Identificador da mensagem.
   * @returns {Observable<{ success: boolean; message?: string }>} Resultado da operação.
   */
  delete(calledId: string, messageId: string): Observable<{ success: boolean; message?: string }> {
    return this.http.delete<{ success: boolean; message?: string }>(
      `${this.baseUrl}/${calledId}/messages/${messageId}`,
    );
  }

  /**
   * Envia um cartão de contato (vCard).
   * @param calledId Identificador do atendimento.
   * @param contact Dados do contato.
   * @returns {Observable<CalledMessageSendResponse>} Mensagem criada.
   */
  sendContact(
    calledId: string,
    contact: {
      fullName: string;
      phoneNumber: string;
      organization?: string;
      email?: string;
      url?: string;
    },
  ): Observable<CalledMessageSendResponse> {
    return this.http.post<CalledMessageSendResponse>(
      `${this.baseUrl}/${calledId}/messages/contact`,
      { contact },
    );
  }

  /**
   * Envia uma localização geográfica.
   * @param calledId Identificador do atendimento.
   * @param location Dados da localização.
   * @returns {Observable<CalledMessageSendResponse>} Mensagem criada.
   */
  sendLocation(
    calledId: string,
    location: {
      latitude: number;
      longitude: number;
      name?: string;
      address?: string;
    },
  ): Observable<CalledMessageSendResponse> {
    return this.http.post<CalledMessageSendResponse>(
      `${this.baseUrl}/${calledId}/messages/location`,
      { location },
    );
  }
  /**
   * Enviar um modelo de mensagem (Template).
   * @param calledId Identificador do atendimento.
   * @param templateName Nome do template.
   * @param parameters Parâmetros do template (components).
   * @param languageCode Código do idioma (default: pt_BR).
   * @returns {Observable<CalledMessageSendResponse>} Mensagem criada.
   */
  sendTemplate(
    calledId: string,
    templateName: string,
    parameters: unknown[] = [],
    languageCode = 'pt_BR',
  ): Observable<CalledMessageSendResponse> {
    const metadata = {
      template: {
        name: templateName,
        language: {
          code: languageCode,
          policy: 'deterministic',
        },
        components: parameters, // Assumes parameters are already in component format or needs a transformer
      },
    };
    return this.send(calledId, templateName, 'template', metadata);
  }

  /**
   * Responde a uma mensagem específica (Quote/Reply).
   * @param calledId Identificador do atendimento.
   * @param messageId Identificador da mensagem a ser respondida.
   * @param content Conteúdo da resposta.
   * @returns {Observable<CalledMessageSendResponse>} Mensagem criada.
   */
  reply(
    calledId: string,
    messageId: string,
    content: string,
  ): Observable<CalledMessageSendResponse> {
    return this.send(calledId, content, 'text', { quoted_message_id: messageId });
  }
}
