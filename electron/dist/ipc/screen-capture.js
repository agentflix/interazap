"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.registerScreenCaptureHandlers = registerScreenCaptureHandlers;
const electron_1 = require("electron");
function registerScreenCaptureHandlers() {
    electron_1.ipcMain.handle('screen:getSources', async () => {
        const sources = await electron_1.desktopCapturer.getSources({
            types: ['window', 'screen'],
            thumbnailSize: { width: 320, height: 180 },
        });
        return sources.map((source) => ({
            id: source.id,
            name: source.name,
            thumbnail: source.thumbnail.toDataURL(),
            display_id: source.display_id,
        }));
    });
}
