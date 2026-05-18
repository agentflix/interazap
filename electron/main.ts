import { app, BrowserWindow } from 'electron';
import * as path from 'path';
import { registerWindowHandlers } from './ipc/window';
import { registerSystemHandlers } from './ipc/system';
import { registerNotificationHandlers } from './ipc/notifications';
import { registerFileSystemHandlers } from './ipc/file-system';
import { registerScreenCaptureHandlers } from './ipc/screen-capture';
import { registerUpdaterHandlers } from './services/updater';
import { createTray, destroyTray } from './tray';
import { registerGlobalShortcuts, unregisterAllShortcuts } from './shortcuts';

let mainWindow: BrowserWindow | null = null;

function createWindow(): void {
  const isMac = process.platform === 'darwin';

  mainWindow = new BrowserWindow({
    width: 1280,
    height: 800,
    minWidth: 1024,
    minHeight: 768,
    frame: true,
    titleBarStyle: isMac ? 'hidden' : 'default',
    backgroundColor: '#fafafa', // canvas / surface-50 — design system token
    webPreferences: {
      nodeIntegration: false,
      contextIsolation: true,
      sandbox: true,
      preload: path.join(__dirname, 'preload.js'),
    },
  });

  // Register all IPC handlers
  registerWindowHandlers();
  registerSystemHandlers();
  registerNotificationHandlers();
  registerFileSystemHandlers();
  registerScreenCaptureHandlers();
  registerUpdaterHandlers();

  // Create system tray
  if (mainWindow) {
    createTray(mainWindow);

    // Register global shortcuts
    registerGlobalShortcuts(() => mainWindow);

    // Send maximized state changes to renderer
    mainWindow.on('maximize', () => {
      mainWindow?.webContents.send('window:maximized-changed', true);
    });
    mainWindow.on('unmaximize', () => {
      mainWindow?.webContents.send('window:maximized-changed', false);
    });
  }

  if (process.env.NODE_ENV === 'production') {
    mainWindow.loadFile(path.join(__dirname, '../app/browser/index.html'));
  } else {
    mainWindow.loadURL('http://localhost:4200');
    mainWindow.webContents.openDevTools();
  }

  mainWindow.on('closed', () => {
    destroyTray();
    unregisterAllShortcuts();
    mainWindow = null;
  });
}

app.whenReady().then(createWindow);

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') {
    app.quit();
  }
});

app.on('activate', () => {
  if (mainWindow === null) {
    createWindow();
  }
});

app.on('will-quit', () => {
  unregisterAllShortcuts();
});
