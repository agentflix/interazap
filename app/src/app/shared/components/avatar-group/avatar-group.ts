import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';

import type { AfAvatarGroupItem } from './avatar-group.model';
export * from './avatar-group.model';



/**
 * Grupo de avatares empilhados com indicador de excedente (+N).
 *
 * Contexto: utilizado em listas de participantes, atribuições de tarefas
 * e qualquer interface que exiba múltiplos usuários de forma compacta.
 *
 * @example
 * ```html
 * <af-avatar-group [items]="usuarios" [max]="4" size="sm" />
 * ```
 */
@Component({
  selector: 'af-avatar-group',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './avatar-group.html',
})
export class AfAvatarGroupComponent {
  /** Lista de avatares a exibir */
  readonly items = input<AfAvatarGroupItem[]>([]);

  /** Máximo de avatares visíveis antes do indicador +N */
  readonly max = input(5);

  /** Tamanho dos avatares */
  readonly size = input<'xs' | 'sm' | 'md' | 'lg'>('md');

  protected readonly visibleItems = computed(() => this.items().slice(0, this.max()));

  protected readonly overflowCount = computed(() => Math.max(0, this.items().length - this.max()));

  protected readonly avatarClasses = computed(() => {
    const sizes: Record<string, string> = {
      xs: 'w-6 h-6',
      sm: 'w-8 h-8',
      md: 'w-10 h-10',
      lg: 'w-12 h-12',
    };
    return `${sizes[this.size()]} rounded-full ring-2 ring-white dark:ring-neutral-900 bg-accent-100 dark:bg-accent-800 flex items-center justify-center`;
  });

  protected initials(name: string): string {
    return name
      .split(' ')
      .map((w) => w[0])
      .join('')
      .toUpperCase()
      .slice(0, 2);
  }
}
