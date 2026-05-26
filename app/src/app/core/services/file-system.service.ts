import { Injectable, inject } from '@angular/core';
import { ElectronService } from './electron.service';
import type { FileFilter } from '@core/models/file-system.model';
export type { FileFilter } from '@core/models/file-system.model';


/**
 * Realiza operações de sistema de arquivos via IPC do Electron.
 *
 * @remarks
 * Encapsula os diálogos de arquivo e APIs de escrita do Electron para fornecer
 * funcionalidades de salvar, abrir e exportar em builds desktop.
 */
@Injectable({
  providedIn: 'root',
})
export class FileSystemService {
  private electronService = inject(ElectronService);

  /**
   * Salva dados binários em um arquivo usando o diálogo de salvar do Electron.
   *
   * @param data - `ArrayBuffer` com o conteúdo do arquivo
   * @param filename - Nome de arquivo padrão para o diálogo
   * @returns Promise com o caminho do arquivo salvo ou `null` se cancelado
   */
  async saveFile(data: ArrayBuffer, filename: string): Promise<string | null> {
    return this.electronService.saveFile(data, filename);
  }

  /**
   * Abre um arquivo usando o diálogo de abertura do Electron.
   *
   * @param filters - Filtros opcionais de tipo de arquivo
   * @returns Promise com caminho e conteúdo do arquivo, ou `null` se cancelado
   */
  async openFile(filters?: FileFilter[]): Promise<{ path: string; content: ArrayBuffer } | null> {
    return this.electronService.openFile({ filters });
  }

  /**
   * Abre uma pasta no explorador de arquivos do sistema.
   *
   * @param path - Caminho da pasta a abrir
   * @returns Promise com o caminho da pasta aberta
   */
  async openFolder(path: string): Promise<string> {
    return this.electronService.openFolder(path);
  }

  /**
   * Exporta dados como arquivo JSON.
   *
   * @param data - Objeto a serializar como JSON
   * @param filename - Nome de arquivo padrão para o diálogo
   * @returns Promise com o caminho do arquivo salvo ou `null` se cancelado
   */
  async exportJson(data: unknown, filename: string): Promise<string | null> {
    const jsonString = JSON.stringify(data, null, 2);
    const encoder = new TextEncoder();
    const arrayBuffer = encoder.encode(jsonString).buffer;
    return this.saveFile(arrayBuffer, filename);
  }

  /**
   * Exporta um array de objetos como arquivo CSV.
   *
   * @param data - Array de objetos a converter para CSV
   * @param filename - Nome de arquivo padrão para o diálogo
   * @returns Promise com o caminho do arquivo salvo ou `null` se não houver dados
   */
  async exportCsv(data: Record<string, unknown>[], filename: string): Promise<string | null> {
    if (data.length === 0) return null;

    const headers = Object.keys(data[0]);
    const csvRows = [
      headers.join(','),
      ...data.map((row) =>
        headers
          .map((header) => {
            const value = row[header];
            const escaped = String(value).replace(/"/g, '""');
            return `"${escaped}"`;
          })
          .join(','),
      ),
    ];

    const csvString = csvRows.join('\n');
    const encoder = new TextEncoder();
    const arrayBuffer = encoder.encode(csvString).buffer;
    return this.saveFile(arrayBuffer, filename);
  }

  /**
   * Importa um arquivo JSON via diálogo de abertura.
   *
   * @returns Promise com os dados JSON analisados ou `null` se cancelado
   */
  async importJson<T>(): Promise<T | null> {
    const result = await this.openFile([{ name: 'JSON', extensions: ['json'] }]);

    if (!result) return null;

    const decoder = new TextDecoder();
    const jsonString = decoder.decode(result.content);
    try {
      return JSON.parse(jsonString) as T;
    } catch {
      console.error('Failed to parse JSON file');
      return null;
    }
  }
}
