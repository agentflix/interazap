export type RecordingState = 'text' | 'recording' | 'paused';

export interface RecordedAudio {
  blob: Blob;
  duration: number;
}
