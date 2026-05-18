import { app, Tray, Menu, BrowserWindow, nativeImage } from 'electron';
import * as path from 'path';

let tray: Tray | null = null;

export function createTray(mainWindow: BrowserWindow): Tray {
    // Load the InteraZap emerald brand icon (#3ecf8e) from build resources
    const iconPath = path.join(__dirname, '../build/icon.png');
    const icon = nativeImage.createFromPath(iconPath).resize({ width: 16, height: 16 });

    tray = new Tray(icon);
    tray.setToolTip('InteraZap Desktop');

    const contextMenu = Menu.buildFromTemplate([
        {
            label: 'Show InteraZap',
            click: () => {
                mainWindow.show();
                mainWindow.focus();
            },
        },
        { type: 'separator' },
        {
            label: 'Start Recording',
            click: () => {
                mainWindow.show();
                mainWindow.webContents.send('recording:start');
            },
        },
        { type: 'separator' },
        {
            label: 'Settings',
            click: () => {
                mainWindow.show();
                mainWindow.webContents.send('navigate', '/settings');
            },
        },
        {
            label: 'Help',
            click: () => {
                mainWindow.show();
                mainWindow.webContents.send('navigate', '/help');
            },
        },
        { type: 'separator' },
        {
            label: 'Quit',
            click: () => {
                app.quit();
            },
        },
    ]);

    tray.setContextMenu(contextMenu);

    tray.on('click', () => {
        if (mainWindow.isVisible()) {
            mainWindow.hide();
        } else {
            mainWindow.show();
            mainWindow.focus();
        }
    });

    tray.on('double-click', () => {
        mainWindow.show();
        mainWindow.focus();
    });

    return tray;
}

export function destroyTray(): void {
    if (tray) {
        tray.destroy();
        tray = null;
    }
}

export function updateTrayBadge(count: number): void {
    if (tray && process.platform === 'darwin') {
        app.dock.setBadge(count > 0 ? String(count) : '');
    }
}
