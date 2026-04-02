"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.registerNotificationHandlers = registerNotificationHandlers;
const electron_1 = require("electron");
function registerNotificationHandlers() {
    electron_1.ipcMain.on('notification:show', (_event, title, body) => {
        if (electron_1.Notification.isSupported()) {
            const notification = new electron_1.Notification({
                title,
                body,
                silent: false,
            });
            notification.show();
        }
    });
}
