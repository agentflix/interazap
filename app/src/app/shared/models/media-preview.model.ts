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
