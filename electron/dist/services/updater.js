"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.registerUpdaterHandlers = registerUpdaterHandlers;
const electron_updater_1 = require("electron-updater");
const electron_1 = require("electron");
function registerUpdaterHandlers() {
    electron_updater_1.autoUpdater.autoDownload = false;
    electron_1.ipcMain.handle('updater:check', async () => {
        try {
            const result = await electron_updater_1.autoUpdater.checkForUpdates();
            return result?.updateInfo ?? null;
        }
        catch {
            return null;
        }
    });
    electron_1.ipcMain.handle('updater:download', async () => {
        try {
            await electron_updater_1.autoUpdater.downloadUpdate();
            return true;
        }
        catch {
            return false;
        }
    });
    electron_1.ipcMain.on('updater:install', () => {
        electron_updater_1.autoUpdater.quitAndInstall();
    });
}
