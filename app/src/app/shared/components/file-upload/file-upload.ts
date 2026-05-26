import { Component, ChangeDetectionStrategy, input, output, signal, computed } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';

import type { AfUploadFile } from './file-upload.model';
export * from './file-upload.model';

/**
 * Área de drag & drop para upload de arquivos com lista, barras de progresso
 * e botões de cancelamento. Suporta upload de arquivo único ou múltiplos arquivos.
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
  /** ID único para vinculação do rótulo ao input de arquivo */
  protected readonly inputId = `af-file-upload-${Math.random().toString(36).slice(2, 10)}`;

  /** Texto do rótulo */
  readonly label = input<string>();

  /** Classe CSS do contêiner */
  readonly classContainer = input<string>('mb-4');

  /** Tipos de arquivo aceitos */
  readonly accept = input<string>('');

  /** Permite múltiplos arquivos */
  readonly multiple = input(false);

  /** Emitido quando arquivos são selecionados */
  readonly filesSelected = output<File[]>();

  /** Lista interna de arquivos */
  protected readonly files = signal<AfUploadFile[]>([]);

  /** Indica se um arraste está ativo sobre a zona */
  private readonly isDragging = signal(false);

  /** Classes dinâmicas da zona de drop */
  protected readonly dropzoneClasses = computed(() => {
    if (this.isDragging()) {
      return 'border-accent-500 bg-accent-50/50 dark:bg-accent-950/20';
    }
    return 'border-neutral-300 dark:border-neutral-600 hover:border-accent-400 dark:hover:border-accent-500 bg-white dark:bg-neutral-900';
  });

  /** Texto de dica gerado a partir do atributo accept */
  protected readonly acceptHint = computed(() => {
    const a = this.accept();
    if (!a) return this.multiple() ? 'Envie múltiplos arquivos' : 'Qualquer tipo de arquivo';
    return `Formatos aceitos: ${a}`;
  });

  /** Trata o evento de arraste sobre a zona */
  protected onDragOver(event: DragEvent): void {
    event.preventDefault();
    event.stopPropagation();
    this.isDragging.set(true);
  }

  /** Trata a saída do arraste da zona */
  protected onDragLeave(event: DragEvent): void {
    event.preventDefault();
    event.stopPropagation();
    this.isDragging.set(false);
  }

  /** Trata o soltar dos arquivos na zona */
  protected onDrop(event: DragEvent): void {
    event.preventDefault();
    event.stopPropagation();
    this.isDragging.set(false);

    const droppedFiles = event.dataTransfer?.files;
    if (droppedFiles) {
      this.addFiles(Array.from(droppedFiles));
    }
  }

  /** Trata a seleção de arquivo pelo input nativo */
  protected onFileSelect(event: Event): void {
    const input = event.target as HTMLInputElement;
    if (input.files) {
      this.addFiles(Array.from(input.files));
      input.value = ''; // Reset for re-selecting same file
    }
  }

  /** Cancela o upload de um arquivo */
  protected cancelFile(id: string, event: Event): void {
    event.stopPropagation();
    this.files.update((files) =>
      files.map((f) => (f.id === id ? { ...f, status: 'cancelled' as const, progress: 0 } : f)),
    );
  }

  /** Remove um arquivo da lista */
  protected removeFile(id: string, event: Event): void {
    event.stopPropagation();
    this.files.update((files) => files.filter((f) => f.id !== id));
  }

  /** Formata o tamanho do arquivo para exibição legível */
  protected formatSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1048576).toFixed(1)} MB`;
  }

  /** Adiciona arquivos à lista e simula o progresso de upload */
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

  /** Simula o progresso de upload de um arquivo */
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
