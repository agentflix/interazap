export type MediaPreviewType =
  | 'image'
  | 'video'
  | 'audio'
  | 'document'
  | 'ptt'
  | 'ptv'
  | 'sticker'
  | 'file';

export type MediaPreviewStatus = 'pending' | 'uploading' | 'done' | 'failed';

export interface MediaPreviewItem {
  id: string;
  file: File;
  type: MediaPreviewType;
  caption: string;
  previewUrl: string | null;
  status: MediaPreviewStatus;
  progress: {
    loaded: number;
    total: number;
  };
}

export interface MediaTranscriptionSettings {
  media_transcription_audio_enabled: boolean;
  media_transcription_image_enabled: boolean;
  media_transcription_video_enabled: boolean;
  media_transcription_audio_max_minutes: number;
  media_transcription_image_max_per_message: number;
  media_transcription_video_max_seconds: number;
}

export interface MediaTranscriptionSettingsResponse {
  success: boolean;
  data: MediaTranscriptionSettings;
}
