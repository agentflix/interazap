import type { Tag } from 'src/app/core/services/tag.service';

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

/**
 * Builds a color based on the tag name hash.
 *
 * @param name - The tag name
 * @returns HSL color string
 */
export const buildTagColor = (name: string): string => {
  const hash = Array.from(name).reduce((acc, char) => acc + char.charCodeAt(0), 0);
  const hue = hash % 360;
  return `hsl(${hue}, 70%, 85%)`;
};

/**
 * Builds a TagChip from a Tag service model.
 *
 * @param tag - The Tag from the service
 * @returns A TagChip for UI display
 */
export const buildTagChip = (tag: Tag): TagChip => ({
  id: tag.id,
  name: tag.name,
  color: tag.color || buildTagColor(tag.name),
});
