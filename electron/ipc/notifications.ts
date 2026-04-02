import { ipcMain, Notification } from 'electron';

export function registerNotificationHandlers(): void {
  ipcMain.on('notification:show', (_event, title: string, body: string) => {
    if (Notification.isSupported()) {
      const notification = new Notification({
        title,
        body,
        silent: false,
      });
      notification.show();
    }
  });
}
