/**
 * Modelos e tipos para pré-visualização de mídia.
 */

/** Tipo de mídia suportada na pré-visualização. */
export type MediaPreviewType =
  | 'image'
  | 'video'
  | 'audio'
  | 'document'
  | 'ptt'
  | 'ptv'
  | 'sticker'
  | 'file';

/** Status de upload de mídia. */
export type MediaPreviewStatus = 'pending' | 'uploading' | 'done' | 'failed';

/** Item de mídia para pré-visualização e upload. */
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

/** Configurações de transcrição de mídia por IA. */
export interface MediaTranscriptionSettings {
  media_transcription_audio_enabled: boolean;
  media_transcription_image_enabled: boolean;
  media_transcription_video_enabled: boolean;
  media_transcription_audio_max_minutes: number;
  media_transcription_image_max_per_message: number;
  media_transcription_video_max_seconds: number;
}

/** Resposta da API com configurações de transcrição de mídia. */
export interface MediaTranscriptionSettingsResponse {
  success: boolean;
  data: MediaTranscriptionSettings;
}
