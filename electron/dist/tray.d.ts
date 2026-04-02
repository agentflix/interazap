import { Tray, BrowserWindow } from 'electron';
export declare function createTray(mainWindow: BrowserWindow): Tray;
export declare function destroyTray(): void;
export declare function updateTrayBadge(count: number): void;
