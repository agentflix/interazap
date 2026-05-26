import {
  type ElementRef,
  Component,
  ChangeDetectionStrategy,
  input,
  computed,
  signal,
  viewChild,
} from '@angular/core';
import { type FormControl } from '@angular/forms';
import { AfFormLabelComponent } from '../form-label/form-label';
import { AfFormErrorComponent } from '../form-error/form-error';
import { LucideAngularModule } from 'lucide-angular';
import { resolveInputContainerClass } from '../input-container.util';

/**
 * Campo de arquivo simples com rótulo e exibição de erro.
 *
 * Vincula ao FormControl&lt;FileList | null&gt;.
 *
 * @example
 * ```html
 * <af-file-input [control]="fileCtrl" label="Arquivo" accept=".pdf,.csv" />
 * ```
 */
@Component({
  selector: 'af-file-input',
  standalone: true,
  imports: [AfFormLabelComponent, AfFormErrorComponent, LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './file-input.html',
})
export class AfFileInputComponent {
  /** FormControl para vinculação do FileList */
  readonly control = input<FormControl<FileList | null>>();

  /** Rótulo do campo */
  readonly label = input('');

  /** Tipos de arquivo aceitos */
  readonly accept = input('');

  /** Permite múltiplos arquivos */
  readonly multiple = input(false);

  /** Indica se o campo é obrigatório */
  readonly required = input(false);

  /** Mensagem de erro */
  readonly errorMessage = input('Arquivo obrigatório.');

  /** Classe CSS do contêiner */
  readonly classContainer = input<string | null>(null);

  /** Ativa/desativa o espaçamento vertical padrão */
  readonly spacing = input(true);

  /** Texto auxiliar exibido abaixo do campo */
  readonly helpText = input<string>();

  protected readonly fileName = signal('');
  protected readonly fileRef = viewChild<ElementRef<HTMLInputElement>>('fileRef');

  protected readonly showError = computed(
    () => !!this.control()?.invalid && !!this.control()?.touched,
  );

  protected readonly containerClasses = computed(() =>
    resolveInputContainerClass(this.classContainer(), this.spacing()),
  );

  protected readonly dropzoneClasses = computed(() => {
    const border = this.showError()
      ? 'border-red-500 dark:border-red-400'
      : 'border-neutral-300 dark:border-neutral-600';
    return [
      'flex items-center gap-2 rounded-md border border-dashed',
      'bg-white dark:bg-neutral-900 px-3 py-2.5',
      'hover:border-accent-500 transition-colors cursor-pointer',
      border,
    ].join(' ');
  });

  protected openFileDialog(): void {
    this.fileRef()?.nativeElement?.click();
  }

  protected onFileChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    const files = input.files;
    if (files && files.length > 0) {
      this.fileName.set(files.length === 1 ? files[0].name : `${files.length} arquivos`);
      this.control()?.setValue(files);
    } else {
      this.fileName.set('');
      this.control()?.setValue(null);
    }
    this.control()?.markAsTouched();
  }
}
