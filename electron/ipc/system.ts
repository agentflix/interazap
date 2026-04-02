import { ipcMain, shell, app } from 'electron';

export function registerSystemHandlers(): void {
  ipcMain.handle('app:version', () => {
    return app.getVersion();
  });

  ipcMain.handle('app:openExternal', async (_event, url: string) => {
    // Validate URL before opening
    try {
      const parsedUrl = new URL(url);
      if (parsedUrl.protocol === 'http:' || parsedUrl.protocol === 'https:') {
        await shell.openExternal(url);
        return true;
      }
      return false;
    } catch {
      return false;
    }
  });
}
