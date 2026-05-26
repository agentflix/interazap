import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';
import { type FormControl, ReactiveFormsModule } from '@angular/forms';
import { AfFormLabelComponent } from '../form-label/form-label';
import { AfFormErrorComponent } from '../form-error/form-error';
import { LucideAngularModule } from 'lucide-angular';
import { resolveInputContainerClass } from '../input-container.util';

/**
 * Campo de busca com ícone de lupa à esquerda.
 *
 * Ideal para barras de filtro, toolbars e buscas no cabeçalho.
 *
 * @example
 * ```html
 * <af-search-input
 *   [control]="searchControl"
 *   placeholder="Buscar contatos..."
 * />
 * ```
 */
@Component({
  selector: 'af-search-input, app-search-input',
  standalone: true,
  imports: [ReactiveFormsModule, AfFormLabelComponent, LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './search-input.html',
})
export class AfSearchInputComponent {
  /** FormControl do valor de busca */
  readonly control = input.required<FormControl<string>>();

  /** Texto do rótulo */
  readonly label = input<string>();

  /** Texto placeholder */
  readonly placeholder = input('Buscar...');

  /** Classe CSS do contêiner */
  readonly classContainer = input<string | null>(null);

  /** Ativa/desativa o espaçamento vertical padrão */
  readonly spacing = input(true);

  /** Texto auxiliar exibido abaixo do campo */
  readonly helpText = input<string>();

  /** Tamanho do campo */
  readonly size = input<'sm' | 'md'>('md');

  /** Atributo data-test para testes E2E */
  readonly dataTest = input<string>();

  /** aria-label para acessibilidade */
  readonly ariaLabel = input<string>();

  /** ID único */
  protected readonly inputId = `search-${Math.random().toString(36).slice(2, 9)}`;

  /** Classes CSS do contêiner */
  protected readonly containerClasses = computed(() =>
    resolveInputContainerClass(this.classContainer(), this.spacing()),
  );

  /** Tamanho do ícone baseado no tamanho do campo */
  protected readonly iconSize = computed(() => (this.size() === 'sm' ? 16 : 18));

  /** Classes CSS dinâmicas */
  protected readonly inputClasses = computed(() => {
    const sizeClasses = this.size() === 'sm' ? 'h-8 pl-9 pr-8 text-xs' : 'h-10 pl-10 pr-9 text-sm';

    return [
      'w-full rounded-md border bg-white dark:bg-neutral-900',
      'text-neutral-900 dark:text-neutral-50',
      'placeholder:text-neutral-400 dark:placeholder:text-neutral-500',
      'transition-colors duration-150',
      'focus:outline-none focus:ring-2 focus:ring-accent-500/30 focus:border-accent-500',
      'border-neutral-300 dark:border-neutral-600',
      sizeClasses,
    ].join(' ');
  });

  /** Limpa o valor de busca */
  protected clear(): void {
    this.control().setValue('');
    this.control().markAsTouched();
  }
}

export const SearchInputComponent = AfSearchInputComponent;
