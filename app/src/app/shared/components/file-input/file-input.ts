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
 * AfFileInputComponent — Simple file input with label and error display.
 * Binds to a FormControl<FileList | null>.
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
  /** FormControl for FileList binding */
  readonly control = input<FormControl<FileList | null>>();

  /** Field label */
  readonly label = input('');

  /** Accepted file types */
  readonly accept = input('');

  /** Allow multiple files */
  readonly multiple = input(false);

  /** Required field */
  readonly required = input(false);

  /** Error message */
  readonly errorMessage = input('Arquivo obrigatório.');

  /** Container CSS class */
  readonly classContainer = input<string | null>(null);

  /** Enables/disables default vertical spacing */
  readonly spacing = input(true);

  /** Optional helper text displayed below the input */
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
