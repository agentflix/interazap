import {
  Component,
  ChangeDetectionStrategy,
  input,
  computed,
  signal,
  inject,
  ElementRef,
  effect,
  type OnDestroy,
} from '@angular/core';
import { type FormControl, ReactiveFormsModule, Validators } from '@angular/forms';
import { AfFormLabelComponent } from '../form-label/form-label';
import { AfFormErrorComponent } from '../form-error/form-error';
import { LucideAngularModule } from 'lucide-angular';
import { AfScrollAreaComponent } from '../scroll-area/scroll-area';
import { resolveInputContainerClass } from '../input-container.util';

import type { AfSelectOption, SelectOption } from './select-input.model';
export * from './select-input.model';

/**
 * Dropdown de seleção com busca para o UI Kit do InteraZap.
 *
 * Dropdown personalizado com filtro de busca no topo.
 * As opções são filtradas em tempo real. Seleção única com sincronização via FormControl.
 *
 * @example
 * ```html
 * <af-select-input
 *   [control]="statusControl"
 *   label="Status"
 *   [options]="statusOptions"
 *   placeholder="Selecione..."
 * />
 * ```
 */
@Component({
  selector: 'af-select-input, app-select-input',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    AfFormLabelComponent,
    AfFormErrorComponent,
    LucideAngularModule,
    AfScrollAreaComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './select-input.html',
  host: {
    '(document:click)': 'onDocumentClick()',
    '(window:scroll)': 'onWindowScroll()',
    '(window:resize)': 'onWindowResize()',
  },
})
export class AfSelectInputComponent implements OnDestroy {
  /** Força recomputações quando estado/valor do FormControl muda */
  private readonly controlRevision = signal(0);

  /** FormControl for the selected value */
  readonly control = input.required<FormControl<string | number | null>>();

  /** Opções disponíveis */
  readonly options = input.required<readonly AfSelectOption[]>();

  /** Texto do rótulo */
  readonly label = input<string>();

  /** Tamanho do campo — 'sm' corresponde a h-8 (32px), 'md' corresponde a h-10 (40px) */
  readonly size = input<'sm' | 'md'>('md');

  /** Classe CSS do contêiner */
  readonly classContainer = input<string | null>(null);

  /** Ativa/desativa o espaçamento vertical padrão */
  readonly spacing = input(true);

  /** Texto auxiliar exibido abaixo do campo */
  readonly helpText = input<string>();

  /** Placeholder quando nada está selecionado */
  readonly placeholder = input('Selecione...');

  /** Alias legado de placeholder */
  readonly emptyLabel = input<string | undefined>(undefined);

  /** Alias legado de classe do trigger */
  readonly classSelect = input('');

  /** Indica se o dropdown possui filtro de busca */
  readonly searchable = input(true);

  /** Asterisco de campo obrigatório */
  readonly required = input(false);

  /** Mensagem de erro */
  readonly errorMessage = input('Selecione uma opção.');

  /** Atributo data-test */
  readonly dataTest = input<string>();

  /** Rótulo acessível para leitores de tela */
  readonly ariaLabel = input<string>();

  /** Role acessível para o trigger */
  readonly role = input<string>('combobox');

  /** Estado de abertura */
  protected readonly isOpen = signal(false);

  /** Termo de busca */
  protected readonly searchQuery = signal('');

  /** Rótulo da opção atualmente selecionada */
  protected selectedLabel(): string {
    this.controlRevision();
    const selectedValue = this.control().value;
    const selectedOption = this.options().find(
      (option) => String(option.value) === String(selectedValue),
    );
    return selectedOption?.label ?? '';
  }

  /** ID único */
  protected readonly inputId = `select-${Math.random().toString(36).slice(2, 9)}`;

  /** Posição fixa do dropdown */
  protected readonly dropdownPos = signal({ top: 0, left: 0, width: 0 });

  private readonly elRef = inject(ElementRef);

  /** Limpadores de eventos de scroll nos ancestrais */
  private scrollCleanups: (() => void)[] = [];

  /** Classes CSS do contêiner */
  protected readonly containerClasses = computed(() =>
    resolveInputContainerClass(this.classContainer(), this.spacing()),
  );

  /** Opções filtradas pelo termo de busca */
  protected readonly filteredOptions = computed(() => {
    const query = this.searchQuery().toLowerCase();
    if (!query) return this.options();
    return this.options().filter((o) => o.label.toLowerCase().includes(query));
  });

  /** Classes CSS dinâmicas do trigger */
  protected readonly triggerClasses = computed(() => {
    const borderColor = this.showError()
      ? 'border-red-500 dark:border-red-400'
      : 'border-neutral-300 dark:border-neutral-600';

    const sizeClasses = this.size() === 'sm' ? 'h-8 px-3 text-xs' : 'h-10 px-3 text-sm';

    const disabledClasses = this.isDisabled()
      ? 'opacity-50 cursor-not-allowed pointer-events-none'
      : '';

    return [
      'w-full rounded-md border',
      sizeClasses,
      'bg-white dark:bg-neutral-900',
      'flex items-center justify-between gap-2',
      'transition-colors',
      this.isDisabled() ? '' : 'cursor-pointer',
      'focus:outline-none focus:ring-2 focus:ring-accent-500/30 focus:border-accent-500',
      borderColor,
      this.classSelect(),
      disabledClasses,
    ].join(' ');
  });

  /** Indica se deve exibir erro */
  protected readonly showError = computed(() => {
    this.controlRevision();
    return !!this.control()?.invalid && !!this.control()?.touched;
  });

  /** Mensagem de erro: erro do servidor tem precedência sobre o input errorMessage */
  protected readonly resolvedErrorMessage = computed(() => {
    this.controlRevision();
    const serverMsg = this.control()?.errors?.['server'];
    return typeof serverMsg === 'string' ? serverMsg : this.errorMessage();
  });

  /** Verdadeiro quando o control possui Validators.required (ou o input required está definido) */
  protected readonly isRequired = computed(() => {
    this.controlRevision();
    return this.required() || !!this.control()?.hasValidator(Validators.required);
  });

  /** Indica se o control está desabilitado */
  protected readonly isDisabled = computed(() => {
    this.controlRevision();
    return this.control()?.disabled ?? false;
  });

  constructor() {
    effect((onCleanup) => {
      const subscription = this.control().events.subscribe(() => {
        this.controlRevision.update((current) => current + 1);
      });
      onCleanup(() => subscription.unsubscribe());
    });
  }

  /** Alterna o dropdown */
  protected toggle(event: Event): void {
    if (this.isDisabled()) {
      event.preventDefault();
      event.stopPropagation();
      return;
    }
    event.stopPropagation();
    this.isOpen.update((v) => !v);
    if (this.isOpen()) {
      this.updateDropdownPosition();
      this.attachScrollListeners();
    } else {
      this.searchQuery.set('');
      this.detachScrollListeners();
    }
  }

  /** Seleciona uma opção */
  protected selectOption(option: AfSelectOption, event: Event): void {
    event.stopPropagation();
    this.control().setValue(option.value);
    this.control().markAsTouched();
    this.isOpen.set(false);
    this.searchQuery.set('');
  }

  /** Compara valores selecionados de forma segura (string/number) */
  protected isSelected(optionValue: string | number): boolean {
    const selectedValue = this.control().value;
    if (selectedValue === null) {
      return false;
    }

    return String(optionValue) === String(selectedValue);
  }

  /** Filtra as opções */
  protected onSearch(event: Event): void {
    this.searchQuery.set((event.target as HTMLInputElement).value);
  }

  /** Fecha ao clicar fora */
  protected onDocumentClick(): void {
    if (this.isOpen()) {
      this.isOpen.set(false);
      this.searchQuery.set('');
      this.detachScrollListeners();
    }
  }

  /** Reposiciona ou fecha ao rolar a janela */
  protected onWindowScroll(): void {
    if (this.isOpen()) {
      this.updateDropdownPosition();
    }
  }

  /** Reposiciona ou fecha ao redimensionar a janela */
  protected onWindowResize(): void {
    if (this.isOpen()) {
      this.updateDropdownPosition();
    }
  }

  /** Calcula a posição do dropdown baseado no getBoundingClientRect do trigger */
  private updateDropdownPosition(): void {
    const trigger: HTMLElement | null =
      this.elRef.nativeElement.querySelector('button[aria-haspopup]');
    if (!trigger) return;

    const rect = trigger.getBoundingClientRect();
    const gap = 4;
    const dropdownHeight = 220; // max-h-48 (192px) + search bar (~28px)

    // Verifica se há espaço abaixo; caso contrário, posiciona acima
    const spaceBelow = window.innerHeight - rect.bottom;
    const top =
      spaceBelow >= dropdownHeight + gap ? rect.bottom + gap : rect.top - dropdownHeight - gap;

    this.dropdownPos.set({
      top: Math.max(0, top),
      left: rect.left,
      width: rect.width,
    });
  }

  /** Anexa listeners de scroll a todos os ancestrais com scroll */
  private attachScrollListeners(): void {
    this.detachScrollListeners();

    let parent = this.elRef.nativeElement.parentElement;
    while (parent) {
      const style = window.getComputedStyle(parent);
      const overflow = style.overflow + style.overflowY;
      if (overflow.includes('auto') || overflow.includes('scroll')) {
        const handler = () => this.updateDropdownPosition();
        parent.addEventListener('scroll', handler, { passive: true });
        const el = parent;
        this.scrollCleanups.push(() => el.removeEventListener('scroll', handler));
      }
      parent = parent.parentElement;
    }
  }

  /** Remove todos os listeners de scroll */
  private detachScrollListeners(): void {
    this.scrollCleanups.forEach((fn) => fn());
    this.scrollCleanups = [];
  }

  ngOnDestroy(): void {
    this.detachScrollListeners();
  }
}

export const SelectInputComponent = AfSelectInputComponent;
