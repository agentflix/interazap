"use strict";
var __createBinding = (this && this.__createBinding) || (Object.create ? (function(o, m, k, k2) {
    if (k2 === undefined) k2 = k;
    var desc = Object.getOwnPropertyDescriptor(m, k);
    if (!desc || ("get" in desc ? !m.__esModule : desc.writable || desc.configurable)) {
      desc = { enumerable: true, get: function() { return m[k]; } };
    }
    Object.defineProperty(o, k2, desc);
}) : (function(o, m, k, k2) {
    if (k2 === undefined) k2 = k;
    o[k2] = m[k];
}));
var __setModuleDefault = (this && this.__setModuleDefault) || (Object.create ? (function(o, v) {
    Object.defineProperty(o, "default", { enumerable: true, value: v });
}) : function(o, v) {
    o["default"] = v;
});
var __importStar = (this && this.__importStar) || (function () {
    var ownKeys = function(o) {
        ownKeys = Object.getOwnPropertyNames || function (o) {
            var ar = [];
            for (var k in o) if (Object.prototype.hasOwnProperty.call(o, k)) ar[ar.length] = k;
            return ar;
        };
        return ownKeys(o);
    };
    return function (mod) {
        if (mod && mod.__esModule) return mod;
        var result = {};
        if (mod != null) for (var k = ownKeys(mod), i = 0; i < k.length; i++) if (k[i] !== "default") __createBinding(result, mod, k[i]);
        __setModuleDefault(result, mod);
        return result;
    };
})();
Object.defineProperty(exports, "__esModule", { value: true });
exports.registerFileSystemHandlers = registerFileSystemHandlers;
const electron_1 = require("electron");
const fs = __importStar(require("fs"));
function registerFileSystemHandlers() {
    electron_1.ipcMain.handle('fs:saveFile', async (_event, data, filename) => {
        const result = await electron_1.dialog.showSaveDialog({
            defaultPath: filename,
            filters: [{ name: 'All Files', extensions: ['*'] }],
        });
        if (result.canceled || !result.filePath) {
            return null;
        }
        const buffer = Buffer.from(data);
        fs.writeFileSync(result.filePath, buffer);
        return result.filePath;
    });
    electron_1.ipcMain.handle('fs:openFile', async (_event, options) => {
        const result = await electron_1.dialog.showOpenDialog({
            properties: ['openFile'],
            filters: options.filters || [{ name: 'All Files', extensions: ['*'] }],
        });
        if (result.canceled || result.filePaths.length === 0) {
            return null;
        }
        const filePath = result.filePaths[0];
        const content = fs.readFileSync(filePath);
        return {
            path: filePath,
            content: content.buffer.slice(content.byteOffset, content.byteOffset + content.byteLength),
        };
    });
    electron_1.ipcMain.handle('fs:openFolder', async (_event, folderPath) => {
        const { shell } = await Promise.resolve().then(() => __importStar(require('electron')));
        return shell.openPath(folderPath);
    });
}
