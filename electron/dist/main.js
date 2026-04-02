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
const electron_1 = require("electron");
const path = __importStar(require("path"));
const window_1 = require("./ipc/window");
const system_1 = require("./ipc/system");
const notifications_1 = require("./ipc/notifications");
const file_system_1 = require("./ipc/file-system");
const screen_capture_1 = require("./ipc/screen-capture");
const updater_1 = require("./services/updater");
const tray_1 = require("./tray");
const shortcuts_1 = require("./shortcuts");
let mainWindow = null;
function createWindow() {
    const isMac = process.platform === 'darwin';
    mainWindow = new electron_1.BrowserWindow({
        width: 1280,
        height: 800,
        minWidth: 1024,
        minHeight: 768,
        frame: true,
        titleBarStyle: isMac ? 'hidden' : 'default',
        webPreferences: {
            nodeIntegration: false,
            contextIsolation: true,
            sandbox: true,
            preload: path.join(__dirname, 'preload.js'),
        },
    });
    // Register all IPC handlers
    (0, window_1.registerWindowHandlers)();
    (0, system_1.registerSystemHandlers)();
    (0, notifications_1.registerNotificationHandlers)();
    (0, file_system_1.registerFileSystemHandlers)();
    (0, screen_capture_1.registerScreenCaptureHandlers)();
    (0, updater_1.registerUpdaterHandlers)();
    // Create system tray
    if (mainWindow) {
        (0, tray_1.createTray)(mainWindow);
        // Register global shortcuts
        (0, shortcuts_1.registerGlobalShortcuts)(() => mainWindow);
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
    }
    else {
        mainWindow.loadURL('http://localhost:4200');
        mainWindow.webContents.openDevTools();
    }
    mainWindow.on('closed', () => {
        (0, tray_1.destroyTray)();
        (0, shortcuts_1.unregisterAllShortcuts)();
        mainWindow = null;
    });
}
electron_1.app.whenReady().then(createWindow);
electron_1.app.on('window-all-closed', () => {
    if (process.platform !== 'darwin') {
        electron_1.app.quit();
    }
});
electron_1.app.on('activate', () => {
    if (mainWindow === null) {
        createWindow();
    }
});
electron_1.app.on('will-quit', () => {
    (0, shortcuts_1.unregisterAllShortcuts)();
});
