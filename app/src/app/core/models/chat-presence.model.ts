export interface PresencePayload {
  presence: 'composing' | 'recording' | 'paused';
  delay?: number;
}

export interface PresenceResponse {
  success: boolean;
  message: string;
  data?: unknown;
}
