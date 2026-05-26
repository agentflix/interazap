import {
  type ElementRef,
  Component,
  ChangeDetectionStrategy,
  input,
  output,
  signal,
  computed,
  HostListener,
  viewChild,
} from '@angular/core';
import { type FormControl, ReactiveFormsModule } from '@angular/forms';
import { AfFormLabelComponent } from '../form-label/form-label';
import { LucideAngularModule } from 'lucide-angular';
import { AfScrollAreaComponent } from '../scroll-area/scroll-area';
import { resolveInputContainerClass } from '../input-container.util';

import type { AfAutocompleteOption } from './autocomplete.model';
export * from './autocomplete.model';



/**
 * Campo de texto com menu suspenso de sugestões filtradas dinamicamente.
 *
 * Contexto: utilizado em formulários de busca e seleção onde o conjunto
 * de opções é extenso, como cidades, usuários e categorias.
 *
 * @example
 * ```html
 * <af-autocomplete
 *   [control]="cidadeCtrl"
 *   [options]="cidades"
 *   label="Cidade"
 *   (optionSelected)="onCidade($event)"
 * />
 * ```
 */
@Component({
  selector: 'af-autocomplete',
  standalone: true,
  imports: [ReactiveFormsModule, AfFormLabelComponent, LucideAngularModule, AfScrollAreaComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './autocomplete.html',
})
export class AfAutocompleteComponent {
  /** FormControl do campo de texto */
  readonly control = input.required<FormControl<string>>();

  /** Lista de opções disponíveis */
  readonly options = input<AfAutocompleteOption[]>([]);

  /** Rótulo do campo */
  readonly label = input('');

  /** Texto de placeholder */
  readonly placeholder = input('Buscar...');

  /** Exibe asterisco de campo obrigatório */
  readonly required = input(false);

  /** Classe CSS do contêiner */
  readonly classContainer = input<string | null>(null);

  /** Ativa/desativa espaçamento vertical padrão */
  readonly spacing = input(true);

  /** Texto auxiliar exibido abaixo do campo */
  readonly helpText = input<string>();

  /** Emitido quando o usuário seleciona uma opção */
  readonly optionSelected = output<string>();

  protected readonly showDropdown = signal(false);

  protected readonly containerClasses = computed(() =>
    resolveInputContainerClass(this.classContainer(), this.spacing()),
  );

  private readonly rootRef = viewChild<ElementRef<HTMLElement>>('root');

  protected readonly filtered = computed(() => {
    const term = this.control().value?.toLowerCase() ?? '';
    if (!term) return this.options().slice(0, 20);
    return this.options()
      .filter((o) => o.label.toLowerCase().includes(term))
      .slice(0, 20);
  });

  protected onInput(): void {
    this.showDropdown.set(true);
  }

  protected selectOption(opt: AfAutocompleteOption): void {
    this.control().setValue(opt.label);
    this.optionSelected.emit(opt.value);
    this.showDropdown.set(false);
  }

  @HostListener('document:click', ['$event'])
  protected onDocumentClick(event: MouseEvent): void {
    const root = this.rootRef()?.nativeElement;
    if (root && event.target instanceof Node && !root.contains(event.target)) {
      this.showDropdown.set(false);
    }
  }
}
