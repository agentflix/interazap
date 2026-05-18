/**
 * Represents a tag attached to a contact.
 */
export interface ContactTag {
  id: string;
  name: string;
}

/**
 * Represents a tag chip for UI display with color.
 */
export interface TagChip {
  id?: string;
  name: string;
  color: string;
}
