import { app, Tray, Menu, BrowserWindow, nativeImage } from 'electron';
import * as path from 'path';

let tray: Tray | null = null;

export function createTray(mainWindow: BrowserWindow): Tray {
    // Create a simple tray icon
    // Note: In production, you'd use an actual icon file
    const icon = nativeImage.createFromDataURL(
        'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAAAbwAAAG8B8aLcQwAAABl0RVh0U29mdHdhcmUAd3d3Lmlua3NjYXBlLm9yZ5vuPBoAAAE8SURBVDiNpZM9SwNBEIaf3SQXYxPBgqCNYGEjFoKFhYV/gI2Njf8BLGzEwsrCxsLCP8DGxsbCwsLGwkKwsLAQLCwsBAuRBRFzXu9ibOIuuXgkFwhvdp7dM7M7O1u0gP9OoLU+C4RfhfM+8B5oA5P+/j5wB/SAl0BvmqYHkjT5b4DW+gAw8Qe4DLSBBaAF1P39t8AhcAycgRfg0V8g7wB2gXtg3gfWgW1gDqgB08A0UPH3XwPXwDnwDOwD58BboO0PcAXMA1fAA3ANnAFv/gC5ABaAa+AauAbO/cn5BGwBC8A1cAOcA4/AGbAJ7AD7wDFwCFz5E/MJ2AEW/AlyClwDZ8ALf2I2gW1gHjgHToAj4NQfnzEwB+z6E+QR2AR2/QlyBtwAp8Ar0PcnZxLYAnb9CXIKXPkTswlsA7v+BDkFbrwEOfQnZtOfoA1g05+gZ8C2P0EbnqANYN2foA1g3Z+gDWAD2PAn6C9g0x+gvh+gvkKkL8/8AAAAAElFTkSuQmCC',
    );

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
