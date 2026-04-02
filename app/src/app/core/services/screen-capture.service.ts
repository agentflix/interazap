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
 * Service for screen capture and recording in Electron environments.
 *
 * @remarks
 * Wraps the Electron desktopCapture API and MediaRecorder to provide
 * screen recording functionality with file saving capabilities.
 */
@Injectable({
  providedIn: 'root',
})
export class ScreenCaptureService {
  private electronService = inject(ElectronService);

  /**
   * Retrieves available screen sources (displays/windows) for capture.
   *
   * @returns Promise resolving to array of ScreenSource objects
   */
  async getScreenSources(): Promise<ScreenSource[]> {
    return this.electronService.getScreenSources();
  }

  /**
   * Captures a screen or window using the specified source ID.
   *
   * @param sourceId - The desktopCapture source ID to capture
   * @returns Promise resolving to MediaStream or null if capture fails
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
   * Creates a MediaRecorder instance for the given stream.
   *
   * @param stream - The MediaStream to record
   * @returns MediaRecorder instance or null if creation fails
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
   * Saves a recorded blob to disk via Electron's file save dialog.
   *
   * @param blob - The recorded blob data
   * @param filename - The desired filename for the saved file
   * @returns Promise resolving to saved file path or null on failure
   */
  async saveRecording(blob: Blob, filename: string): Promise<string | null> {
    const arrayBuffer = await blob.arrayBuffer();
    return this.electronService.saveFile(arrayBuffer, filename);
  }
}
