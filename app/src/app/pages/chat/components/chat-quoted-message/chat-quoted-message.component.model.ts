/** Modelos e tipos do componente de mensagem citada (reply) do chat. */

/** Dados da mensagem original referenciada em uma resposta. */
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
