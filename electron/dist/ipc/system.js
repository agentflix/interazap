"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.registerSystemHandlers = registerSystemHandlers;
const electron_1 = require("electron");
function registerSystemHandlers() {
    electron_1.ipcMain.handle('app:version', () => {
        return electron_1.app.getVersion();
    });
    electron_1.ipcMain.handle('app:openExternal', async (_event, url) => {
        // Validate URL before opening
        try {
            const parsedUrl = new URL(url);
            if (parsedUrl.protocol === 'http:' || parsedUrl.protocol === 'https:') {
                await electron_1.shell.openExternal(url);
                return true;
            }
            return false;
        }
        catch {
            return false;
        }
    });
}
