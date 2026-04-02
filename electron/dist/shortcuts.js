"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.registerGlobalShortcuts = registerGlobalShortcuts;
exports.unregisterAllShortcuts = unregisterAllShortcuts;
exports.getRegisteredShortcuts = getRegisteredShortcuts;
const electron_1 = require("electron");
let registeredShortcuts = [];
function registerGlobalShortcuts(mainWindowGetter) {
    // Ctrl+Shift+A (Windows/Linux) / Cmd+Shift+A (macOS) - Show/Hide
    const showHideShortcut = process.platform === 'darwin' ? 'Command+Shift+A' : 'Control+Shift+A';
    const showHideRegistered = electron_1.globalShortcut.register(showHideShortcut, () => {
        const mainWindow = mainWindowGetter();
        if (mainWindow) {
            if (mainWindow.isVisible()) {
                mainWindow.hide();
            }
            else {
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
    const recordingRegistered = electron_1.globalShortcut.register(recordingShortcut, () => {
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
function unregisterAllShortcuts() {
    registeredShortcuts.forEach((shortcut) => {
        electron_1.globalShortcut.unregister(shortcut);
    });
    registeredShortcuts = [];
    electron_1.globalShortcut.unregisterAll();
}
function getRegisteredShortcuts() {
    return [...registeredShortcuts];
}
