"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.registerWindowHandlers = registerWindowHandlers;
const electron_1 = require("electron");
function registerWindowHandlers() {
    electron_1.ipcMain.on('window:minimize', (event) => {
        const win = electron_1.BrowserWindow.fromWebContents(event.sender);
        win?.minimize();
    });
    electron_1.ipcMain.on('window:maximize', (event) => {
        const win = electron_1.BrowserWindow.fromWebContents(event.sender);
        if (win?.isMaximized()) {
            win.unmaximize();
        }
        else {
            win?.maximize();
        }
    });
    electron_1.ipcMain.on('window:close', (event) => {
        const win = electron_1.BrowserWindow.fromWebContents(event.sender);
        win?.close();
    });
    electron_1.ipcMain.handle('window:isMaximized', (event) => {
        const win = electron_1.BrowserWindow.fromWebContents(event.sender);
        return win?.isMaximized() ?? false;
    });
}
