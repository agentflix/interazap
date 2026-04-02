import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';

/**
 * Settings for media transcription per tenant.
 */
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

/**
 * Service for managing media transcription configuration.
 *
 * @description Handles GET/PUT for tenant‐level media transcription settings
 * (audio, image, video toggles and limits).
 */
@Injectable({
  providedIn: 'root',
})
export class MediaTranscriptionService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = `${environment.apiUrl}/media-transcription`;

  /**
   * Fetch current media transcription settings for the tenant.
   */
  show(): Observable<MediaTranscriptionSettingsResponse> {
    return this.http.get<MediaTranscriptionSettingsResponse>(this.apiUrl);
  }

  /**
   * Update media transcription settings for the tenant.
   */
  update(data: MediaTranscriptionSettings): Observable<MediaTranscriptionSettingsResponse> {
    return this.http.put<MediaTranscriptionSettingsResponse>(this.apiUrl, data);
  }
}
