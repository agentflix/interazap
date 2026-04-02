import { Component, ChangeDetectionStrategy, input, output, signal, computed } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';

import type { AfUploadFile } from './file-upload.model';
export * from './file-upload.model';

/**
 * File upload component for InteraZap UI Kit.
 *
 * @description Drag & drop zone with file list, progress bars,
 * and cancel buttons. Supports single and multi-file upload.
 *
 * @example
 * ```html
 * <af-file-upload
 *   label="Documentos"
 *   [multiple]="true"
 *   accept=".pdf,.doc,.docx"
 *   (filesSelected)="onFiles($event)"
 * />
 * ```
 */
@Component({
  selector: 'af-file-upload',
  standalone: true,
  imports: [LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './file-upload.html',
})
export class AfFileUploadComponent {
  /** Unique id used to bind the label and file input */
  protected readonly inputId = `af-file-upload-${Math.random().toString(36).slice(2, 10)}`;

  /** Label text */
  readonly label = input<string>();

  /** Container CSS class */
  readonly classContainer = input<string>('mb-4');

  /** Accept file types */
  readonly accept = input<string>('');

  /** Allow multiple files */
  readonly multiple = input(false);

  /** Emitted when files are selected */
  readonly filesSelected = output<File[]>();

  /** Internal file list */
  protected readonly files = signal<AfUploadFile[]>([]);

  /** Whether drag is active */
  private readonly isDragging = signal(false);

  /** Dynamic dropzone classes */
  protected readonly dropzoneClasses = computed(() => {
    if (this.isDragging()) {
      return 'border-accent-500 bg-accent-50/50 dark:bg-accent-950/20';
    }
    return 'border-neutral-300 dark:border-neutral-600 hover:border-accent-400 dark:hover:border-accent-500 bg-white dark:bg-neutral-900';
  });

  /** Hint text from accept attribute */
  protected readonly acceptHint = computed(() => {
    const a = this.accept();
    if (!a) return this.multiple() ? 'Envie múltiplos arquivos' : 'Qualquer tipo de arquivo';
    return `Formatos aceitos: ${a}`;
  });

  /** Handle drag over */
  protected onDragOver(event: DragEvent): void {
    event.preventDefault();
    event.stopPropagation();
    this.isDragging.set(true);
  }

  /** Handle drag leave */
  protected onDragLeave(event: DragEvent): void {
    event.preventDefault();
    event.stopPropagation();
    this.isDragging.set(false);
  }

  /** Handle drop */
  protected onDrop(event: DragEvent): void {
    event.preventDefault();
    event.stopPropagation();
    this.isDragging.set(false);

    const droppedFiles = event.dataTransfer?.files;
    if (droppedFiles) {
      this.addFiles(Array.from(droppedFiles));
    }
  }

  /** Handle native file input change */
  protected onFileSelect(event: Event): void {
    const input = event.target as HTMLInputElement;
    if (input.files) {
      this.addFiles(Array.from(input.files));
      input.value = ''; // Reset for re-selecting same file
    }
  }

  /** Cancel a file upload */
  protected cancelFile(id: string, event: Event): void {
    event.stopPropagation();
    this.files.update((files) =>
      files.map((f) => (f.id === id ? { ...f, status: 'cancelled' as const, progress: 0 } : f)),
    );
  }

  /** Remove a file from the list */
  protected removeFile(id: string, event: Event): void {
    event.stopPropagation();
    this.files.update((files) => files.filter((f) => f.id !== id));
  }

  /** Format file size to human readable */
  protected formatSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1048576).toFixed(1)} MB`;
  }

  /** Add files to the list and simulate upload */
  private addFiles(fileList: File[]): void {
    const filesToAdd = this.multiple() ? fileList : [fileList[0]];
    const newFiles: AfUploadFile[] = filesToAdd.map((file) => ({
      file,
      name: file.name,
      size: file.size,
      progress: 0,
      status: 'pending' as const,
      id: `file-${Math.random().toString(36).slice(2, 9)}`,
    }));

    if (!this.multiple()) {
      this.files.set(newFiles);
    } else {
      this.files.update((existing) => [...existing, ...newFiles]);
    }

    this.filesSelected.emit(filesToAdd);

    // Simulate upload progress for each file
    for (const f of newFiles) {
      this.simulateUpload(f.id);
    }
  }

  /** Simulate file upload progress */
  private simulateUpload(id: string): void {
    let progress = 0;
    const interval = setInterval(() => {
      const current = this.files().find((f) => f.id === id);
      if (!current || current.status === 'cancelled') {
        clearInterval(interval);
        return;
      }

      progress += Math.floor(Math.random() * 15) + 5;
      if (progress >= 100) {
        progress = 100;
        clearInterval(interval);
        this.files.update((files) =>
          files.map((f) => (f.id === id ? { ...f, progress: 100, status: 'done' as const } : f)),
        );
      } else {
        this.files.update((files) =>
          files.map((f) => (f.id === id ? { ...f, progress, status: 'uploading' as const } : f)),
        );
      }
    }, 300);
  }
}
