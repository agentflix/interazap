export interface ElectronAPI {
  // Window
  minimizeWindow: () => void;
  maximizeWindow: () => void;
  closeWindow: () => void;
  isMaximized: () => Promise<boolean>;

  // System
  getAppVersion: () => Promise<string>;
  getPlatform: () => string;
  openExternal: (url: string) => Promise<boolean>;

  // Notifications
  showNotification: (title: string, body: string) => void;

  // File System
  saveFile: (data: ArrayBuffer, filename: string) => Promise<string | null>;
  openFile: (options?: { filters?: { name: string; extensions: string[] }[] }) => Promise<{
    path: string;
    content: ArrayBuffer;
  } | null>;
  openFolder: (path: string) => Promise<string>;

  // Screen Capture
  getScreenSources: () => Promise<ScreenSource[]>;

  // Updates
  checkForUpdates: () => Promise<UpdateInfo | null>;
  downloadUpdate: () => Promise<boolean>;
  installUpdate: () => void;

  // Events
  onMaximizedChange: (callback: (isMaximized: boolean) => void) => void;
}

export interface ScreenSource {
  id: string;
  name: string;
  thumbnail: string;
  display_id: string;
}

export interface UpdateInfo {
  version: string;
  releaseDate: string;
}
