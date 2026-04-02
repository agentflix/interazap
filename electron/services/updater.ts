import { autoUpdater } from 'electron-updater';
import { ipcMain } from 'electron';

export function registerUpdaterHandlers(): void {
  autoUpdater.autoDownload = false;

  ipcMain.handle('updater:check', async () => {
    try {
      const result = await autoUpdater.checkForUpdates();
      return result?.updateInfo ?? null;
    } catch {
      return null;
    }
  });

  ipcMain.handle('updater:download', async () => {
    try {
      await autoUpdater.downloadUpdate();
      return true;
    } catch {
      return false;
    }
  });

  ipcMain.on('updater:install', () => {
    autoUpdater.quitAndInstall();
  });
}
