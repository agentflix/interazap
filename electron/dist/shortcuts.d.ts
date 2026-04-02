import { BrowserWindow } from 'electron';
export declare function registerGlobalShortcuts(mainWindowGetter: () => BrowserWindow | null): void;
export declare function unregisterAllShortcuts(): void;
export declare function getRegisteredShortcuts(): string[];
