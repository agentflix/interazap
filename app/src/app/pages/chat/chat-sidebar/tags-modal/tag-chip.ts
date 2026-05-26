import type { Tag } from '@core/models/tag.model';
import type { TagChip } from '@chat/models/tag-chip.model';

export type { ContactTag, TagChip } from '@chat/models/tag-chip.model';

/**
 * Gera uma cor HSL com base no hash do nome da etiqueta.
 *
 * @param name - Nome da etiqueta.
 * @returns String de cor HSL.
 */
export const buildTagColor = (name: string): string => {
  const hash = Array.from(name).reduce((acc, char) => acc + char.charCodeAt(0), 0);
  const hue = hash % 360;
  return `hsl(${hue}, 70%, 85%)`;
};

/**
 * Constrói um TagChip a partir do modelo Tag do serviço.
 *
 * @param tag - Tag retornada pelo serviço.
 * @returns TagChip para exibição na UI.
 */
export const buildTagChip = (tag: Tag): TagChip => ({
  id: tag.id,
  name: tag.name,
  color: tag.color || buildTagColor(tag.name),
});
