import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import type { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { environment } from '@env/environment';

export interface NegotiationFileUser {
  id: string | number;
  name: string;
}

export interface NegotiationFile {
  id: string | number;
  negotiation_id: string | number;
  user_id?: string | number | null;
  name?: string | null;
  filename?: string | null;
  original_name?: string | null;
  path?: string | null;
  mime_type?: string | null;
  size?: number | null;
  formatted_size?: string | null;
  url?: string | null;
  user?: NegotiationFileUser | null;
  created_at?: string | null;
}

interface NegotiationFileApiItem {
  id: string | number;
  crm_negotiation_id: string | number;
  auth_user_id?: string | number | null;
  name?: string | null;
  path?: string | null;
  size?: number | null;
  mime_type?: string | null;
  created_at?: string | null;
}

@Injectable({ providedIn: 'root' })
export class NegotiationFileService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/crm/negotiations`;

  /**
   * Lista todos os arquivos anexados a uma negociacao.
   *
   * @param negotiationId - Identificador da negociacao
   * @returns Observable com array de arquivos da negociacao
   */
  list(negotiationId: string | number): Observable<{ data: { files: NegotiationFile[] } }> {
    return this.http
      .get<{
        data: NegotiationFileApiItem[] | { files?: NegotiationFileApiItem[] };
      }>(`${this.baseUrl}/${negotiationId}/files`)
      .pipe(
        map((response) => {
          const files = Array.isArray(response.data) ? response.data : (response.data.files ?? []);

          return {
            data: {
              files: files.map((file) => this.mapFile(file)),
            },
          };
        }),
      );
  }

  /**
   * Faz upload de um arquivo e anexa a uma negociacao.
   *
   * @param negotiationId - Identificador da negociacao
   * @param file - Objeto File a ser enviado
   * @returns Observable com o arquivo criado no servidor
   */
  upload(negotiationId: string | number, file: File): Observable<{ data: NegotiationFile }> {
    const formData = new FormData();
    formData.append('file', file);
    return this.http
      .post<{ data: NegotiationFileApiItem }>(`${this.baseUrl}/${negotiationId}/files`, formData)
      .pipe(map((response) => ({ data: this.mapFile(response.data) })));
  }

  /**
   * Remove um arquivo de uma negociacao.
   *
   * @param negotiationId - Identificador da negociacao
   * @param fileId - Identificador do arquivo
   * @returns Observable vazio indicando sucesso
   */
  delete(negotiationId: string | number, fileId: string | number): Observable<void> {
    return this.http.delete<void>(`${this.baseUrl}/${negotiationId}/files/${fileId}`);
  }

  private mapFile(file: NegotiationFileApiItem): NegotiationFile {
    return {
      id: file.id,
      negotiation_id: file.crm_negotiation_id,
      user_id: file.auth_user_id ?? null,
      name: file.name ?? null,
      filename: file.name ?? null,
      original_name: file.name ?? null,
      path: file.path ?? null,
      mime_type: file.mime_type ?? null,
      size: file.size ?? null,
      formatted_size: this.formatFileSize(file.size),
      created_at: file.created_at ?? null,
    };
  }

  private formatFileSize(size?: number | null): string {
    if (!size || size <= 0) return '-';
    if (size < 1024) return `${size} B`;
    if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`;
    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
  }
}
