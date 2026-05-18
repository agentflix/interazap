import { Injectable, inject } from '@angular/core';
import { ElectronService } from './electron.service';
import type { FileFilter } from '@core/models/file-system.model';
export type { FileFilter } from '@core/models/file-system.model';


/**
 * Filter for file open dialogs specifying allowed file types.
 */

/**
 * Service for file system operations via Electron IPC.
 *
 * @remarks
 * Wraps Electron's file dialog and file writing APIs to provide
 * save, open, and export functionality for desktop builds.
 */
@Injectable({
  providedIn: 'root',
})
export class FileSystemService {
  private electronService = inject(ElectronService);

  /**
   * Saves binary data to a file using Electron's save dialog.
   *
   * @param data - ArrayBuffer of file content
   * @param filename - Default filename for the save dialog
   * @returns Promise resolving to saved file path or null if cancelled
   */
  async saveFile(data: ArrayBuffer, filename: string): Promise<string | null> {
    return this.electronService.saveFile(data, filename);
  }

  /**
   * Opens a file using Electron's open dialog.
   *
   * @param filters - Optional file type filters
   * @returns Promise resolving to file path and content, or null if cancelled
   */
  async openFile(filters?: FileFilter[]): Promise<{ path: string; content: ArrayBuffer } | null> {
    return this.electronService.openFile({ filters });
  }

  /**
   * Opens a folder in the system file explorer.
   *
   * @param path - Folder path to open
   * @returns Promise resolving to the opened folder path
   */
  async openFolder(path: string): Promise<string> {
    return this.electronService.openFolder(path);
  }

  /**
   * Exports data as a JSON file.
   *
   * @param data - Object to serialize as JSON
   * @param filename - Default filename for the save dialog
   * @returns Promise resolving to saved file path or null if cancelled
   */
  async exportJson(data: unknown, filename: string): Promise<string | null> {
    const jsonString = JSON.stringify(data, null, 2);
    const encoder = new TextEncoder();
    const arrayBuffer = encoder.encode(jsonString).buffer;
    return this.saveFile(arrayBuffer, filename);
  }

  /**
   * Exports an array of objects as a CSV file.
   *
   * @param data - Array of objects to convert to CSV
   * @param filename - Default filename for the save dialog
   * @returns Promise resolving to saved file path or null if no data
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
   * Imports a JSON file via open dialog.
   *
   * @returns Promise resolving to parsed JSON data or null if cancelled
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
