import { ipcMain, desktopCapturer } from 'electron';

export function registerScreenCaptureHandlers(): void {
  ipcMain.handle('screen:getSources', async () => {
    const sources = await desktopCapturer.getSources({
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
