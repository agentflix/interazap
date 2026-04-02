import { ipcMain, dialog } from 'electron';
import * as fs from 'fs';

export function registerFileSystemHandlers(): void {
  ipcMain.handle('fs:saveFile', async (_event, data: ArrayBuffer, filename: string) => {
    const result = await dialog.showSaveDialog({
      defaultPath: filename,
      filters: [{ name: 'All Files', extensions: ['*'] }],
    });

    if (result.canceled || !result.filePath) {
      return null;
    }

    const buffer = Buffer.from(data);
    fs.writeFileSync(result.filePath, buffer);
    return result.filePath;
  });

  ipcMain.handle(
    'fs:openFile',
    async (_event, options: { filters?: { name: string; extensions: string[] }[] }) => {
      const result = await dialog.showOpenDialog({
        properties: ['openFile'],
        filters: options.filters || [{ name: 'All Files', extensions: ['*'] }],
      });

      if (result.canceled || result.filePaths.length === 0) {
        return null;
      }

      const filePath = result.filePaths[0];
      const content = fs.readFileSync(filePath);
      return {
        path: filePath,
        content: content.buffer.slice(content.byteOffset, content.byteOffset + content.byteLength),
      };
    },
  );

  ipcMain.handle('fs:openFolder', async (_event, folderPath: string) => {
    const { shell } = await import('electron');
    return shell.openPath(folderPath);
  });
}
