/**
 * Models and types for chat-quoted-message.component component.
 */

export interface QuotedMessage {
  id?: string | number | null;
  external_id?: string | null;
  content?: string | null;
  type?: string | null;
  direction?: 'incoming' | 'outgoing' | null;
  sender_name?: string | null;
  file_name?: string | null;
  mime_type?: string | null;
  is_edited?: boolean;
  edited_at?: string | null;
}
