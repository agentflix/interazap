import { globalShortcut, BrowserWindow } from 'electron';

let registeredShortcuts: string[] = [];

export function registerGlobalShortcuts(mainWindowGetter: () => BrowserWindow | null): void {
  // Ctrl+Shift+A (Windows/Linux) / Cmd+Shift+A (macOS) - Show/Hide
  const showHideShortcut = process.platform === 'darwin' ? 'Command+Shift+A' : 'Control+Shift+A';

  const showHideRegistered = globalShortcut.register(showHideShortcut, () => {
    const mainWindow = mainWindowGetter();
    if (mainWindow) {
      if (mainWindow.isVisible()) {
        mainWindow.hide();
      } else {
        mainWindow.show();
        mainWindow.focus();
      }
    }
  });

  if (showHideRegistered) {
    registeredShortcuts.push(showHideShortcut);
  }

  // Ctrl+Shift+R (Windows/Linux) / Cmd+Shift+R (macOS) - Start Recording
  const recordingShortcut = process.platform === 'darwin' ? 'Command+Shift+R' : 'Control+Shift+R';

  const recordingRegistered = globalShortcut.register(recordingShortcut, () => {
    const mainWindow = mainWindowGetter();
    if (mainWindow) {
      mainWindow.show();
      mainWindow.focus();
      mainWindow.webContents.send('recording:start');
    }
  });

  if (recordingRegistered) {
    registeredShortcuts.push(recordingShortcut);
  }
}

export function unregisterAllShortcuts(): void {
  registeredShortcuts.forEach((shortcut) => {
    globalShortcut.unregister(shortcut);
  });
  registeredShortcuts = [];
  globalShortcut.unregisterAll();
}

export function getRegisteredShortcuts(): string[] {
  return [...registeredShortcuts];
}
