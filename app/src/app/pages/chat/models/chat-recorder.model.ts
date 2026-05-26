/** Estado atual do gravador de áudio no composer. */
export type RecordingState = 'text' | 'recording' | 'paused';

/** Resultado de uma gravação de áudio concluída. */
export interface RecordedAudio {
  /** Blob do arquivo de áudio gravado. */
  blob: Blob;
  /** Duração em segundos. */
  duration: number;
}
