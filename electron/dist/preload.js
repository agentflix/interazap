"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
const electron_1 = require("electron");
electron_1.contextBridge.exposeInMainWorld('electronAPI', {
    // Window
    minimizeWindow: () => electron_1.ipcRenderer.send('window:minimize'),
    maximizeWindow: () => electron_1.ipcRenderer.send('window:maximize'),
    closeWindow: () => electron_1.ipcRenderer.send('window:close'),
    isMaximized: () => electron_1.ipcRenderer.invoke('window:isMaximized'),
    // System
    getAppVersion: () => electron_1.ipcRenderer.invoke('app:version'),
    getPlatform: () => process.platform,
    openExternal: (url) => electron_1.ipcRenderer.invoke('app:openExternal', url),
    // Notifications
    showNotification: (title, body) => electron_1.ipcRenderer.send('notification:show', title, body),
    // File System
    saveFile: (data, filename) => electron_1.ipcRenderer.invoke('fs:saveFile', data, filename),
    openFile: (options) => electron_1.ipcRenderer.invoke('fs:openFile', options || {}),
    openFolder: (path) => electron_1.ipcRenderer.invoke('fs:openFolder', path),
    // Screen Capture
    getScreenSources: () => electron_1.ipcRenderer.invoke('screen:getSources'),
    // Updates
    checkForUpdates: () => electron_1.ipcRenderer.invoke('updater:check'),
    downloadUpdate: () => electron_1.ipcRenderer.invoke('updater:download'),
    installUpdate: () => electron_1.ipcRenderer.send('updater:install'),
    // Events
    onMaximizedChange: (callback) => {
        electron_1.ipcRenderer.on('window:maximized-changed', (_event, isMaximized) => callback(isMaximized));
    },
});
