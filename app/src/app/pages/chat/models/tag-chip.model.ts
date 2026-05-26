/** Etiqueta vinculada a um contato. */
export interface ContactTag {
  id: string;
  name: string;
}

/** Chip de etiqueta para exibição na UI, com cor calculada. */
export interface TagChip {
  id?: string;
  name: string;
  /** Cor HSL calculada com base no hash do nome da etiqueta. */
  color: string;
}
