import { contextBridge, ipcRenderer } from 'electron';

contextBridge.exposeInMainWorld('electronAPI', {
  // Window
  minimizeWindow: () => ipcRenderer.send('window:minimize'),
  maximizeWindow: () => ipcRenderer.send('window:maximize'),
  closeWindow: () => ipcRenderer.send('window:close'),
  isMaximized: () => ipcRenderer.invoke('window:isMaximized'),

  // System
  getAppVersion: () => ipcRenderer.invoke('app:version'),
  getPlatform: () => process.platform,
  openExternal: (url: string) => ipcRenderer.invoke('app:openExternal', url),

  // Notifications
  showNotification: (title: string, body: string) =>
    ipcRenderer.send('notification:show', title, body),

  // File System
  saveFile: (data: ArrayBuffer, filename: string) =>
    ipcRenderer.invoke('fs:saveFile', data, filename),
  openFile: (options?: { filters?: { name: string; extensions: string[] }[] }) =>
    ipcRenderer.invoke('fs:openFile', options || {}),
  openFolder: (path: string) => ipcRenderer.invoke('fs:openFolder', path),

  // Screen Capture
  getScreenSources: () => ipcRenderer.invoke('screen:getSources'),

  // Updates
  checkForUpdates: () => ipcRenderer.invoke('updater:check'),
  downloadUpdate: () => ipcRenderer.invoke('updater:download'),
  installUpdate: () => ipcRenderer.send('updater:install'),

  // Events
  onMaximizedChange: (callback: (isMaximized: boolean) => void) => {
    ipcRenderer.on('window:maximized-changed', (_event, isMaximized) => callback(isMaximized));
  },
});
