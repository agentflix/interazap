import type { Tag } from '@core/models/tag.model';
import type { TagChip } from '@chat/models/tag-chip.model';

export type { ContactTag, TagChip } from '@chat/models/tag-chip.model';

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
