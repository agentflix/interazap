import { Injectable, inject } from '@angular/core';
import { ElectronService, type ScreenSource } from './electron.service';

/**
 * Constraints used when requesting screen capture via Electron desktopCapture API.
 */
interface ElectronMediaConstraints {
  video: {
    mandatory: {
      chromeMediaSource: string;
      chromeMediaSourceId: string;
      minWidth: number;
      maxWidth: number;
      minHeight: number;
      maxHeight: number;
    };
  };
}

/**
 * Captura e gravação de tela em ambientes Electron.
 *
 * Contexto: wrapper da API desktopCapture do Electron e MediaRecorder para
 * gravação de tela com salvamento de arquivo.
 */
@Injectable({
  providedIn: 'root',
})
export class ScreenCaptureService {
  private electronService = inject(ElectronService);

  /**
   * Retorna as fontes de captura disponíveis (telas/janelas).
   *
   * @returns Promise com array de objetos ScreenSource
   */
  async getScreenSources(): Promise<ScreenSource[]> {
    return this.electronService.getScreenSources();
  }

  /**
   * Captura uma tela ou janela usando o ID de fonte especificado.
   *
   * @param sourceId - ID da fonte desktopCapture para capturar
   * @returns Promise com MediaStream ou null se a captura falhar
   */
  async captureScreen(sourceId: string): Promise<MediaStream | null> {
    try {
      const constraints: MediaStreamConstraints & ElectronMediaConstraints = {
        audio: false,
        video: {
          mandatory: {
            chromeMediaSource: 'desktop',
            chromeMediaSourceId: sourceId,
            minWidth: 1280,
            maxWidth: 4096,
            minHeight: 720,
            maxHeight: 4096,
          },
        },
      };
      const stream = await navigator.mediaDevices.getUserMedia(constraints);
      return stream;
    } catch (error) {
      console.error('Failed to capture screen:', error);
      return null;
    }
  }

  /**
   * Cria uma instância MediaRecorder para o stream informado.
   *
   * @param stream - MediaStream para gravar
   * @returns MediaRecorder ou null se a criação falhar
   */
  startRecording(stream: MediaStream): MediaRecorder | null {
    try {
      const recorder = new MediaRecorder(stream, {
        mimeType: 'video/webm;codecs=vp9',
      });
      return recorder;
    } catch (error) {
      console.error('Failed to start recording:', error);
      return null;
    }
  }

  /**
   * Salva um blob gravado no disco via diálogo de arquivo do Electron.
   *
   * @param blob - Dados blob gravados
   * @param filename - Nome desejado para o arquivo salvo
   * @returns Promise com caminho do arquivo salvo ou null em caso de falha
   */
  async saveRecording(blob: Blob, filename: string): Promise<string | null> {
    const arrayBuffer = await blob.arrayBuffer();
    return this.electronService.saveFile(arrayBuffer, filename);
  }
}
