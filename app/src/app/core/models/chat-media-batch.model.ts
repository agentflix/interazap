import type { CalledMessage } from '@core/models/called-message.model';

export type MediaBatchPhase = 'uploading' | 'uploaded' | 'sent' | 'failed';

export interface MediaBatchEvent {
  id: string;
  phase: MediaBatchPhase;
  loaded?: number;
  total?: number;
  message?: CalledMessage;
}
