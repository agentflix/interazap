/**
 * Represents the 24-hour message window status for a contact.
 *
 * Used to determine whether a contact can receive free-text messages
 * or requires template-based messages via Meta WhatsApp Business API.
 *
 * @example
 * ```typescript
 * const status: WindowStatus = {
 *   canSendFreeText: true,
 *   lastMessageAt: new Date('2026-04-11T10:00:00Z')
 * };
 * ```
 */
export interface WindowStatus {
  /** Whether the contact can receive free-form text messages (within 24h window). */
  canSendFreeText: boolean;
  /** Timestamp of the last message received from the contact, or null if no messages exist. */
  lastMessageAt: Date | null;
}

/**
 * Response wrapper for window status API endpoint.
 */
export interface WindowStatusResponse {
  data: WindowStatus;
}
