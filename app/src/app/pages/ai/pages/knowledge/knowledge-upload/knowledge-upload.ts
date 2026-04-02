import {
  type ElementRef,
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  input,
  inject,
  output,
  signal,
  ViewChild,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { type HttpErrorResponse } from '@angular/common/http';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfButtonComponent,
  AfIconButtonComponent,
  AfLoadingButtonComponent,
  AfPageTitleComponent,
  AfTextInputComponent,
} from '@shared/components';
import { ToastService } from '@core/services/toast.service';
import { AiKnowledgeService } from '@ai/services/ai-knowledge.service';

/**
 * Upload documents to AI knowledge base.
 * Business logic preserved verbatim from source. Visual layer migrated to UI Kit.
 */
@Component({
  selector: 'app-ai-knowledge-upload',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    LucideAngularModule,
    AfPageTitleComponent,
    AfTextInputComponent,
    AfButtonComponent,
    AfLoadingButtonComponent,
    AfIconButtonComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './knowledge-upload.html',
})
export class KnowledgeUploadComponent {
  private readonly fb = inject(FormBuilder);
  private readonly router = inject(Router);
  private readonly knowledgeService = inject(AiKnowledgeService);
  private readonly toast = inject(ToastService);
  private readonly destroyRef = inject(DestroyRef);

  readonly embedded = input(false);
  readonly saved = output<void>();
  readonly cancelled = output<void>();

  @ViewChild('fileInput') fileInput!: ElementRef<HTMLInputElement>;

  readonly isUploading = signal(false);
  readonly isDragging = signal(false);
  readonly selectedFile = signal<File | null>(null);
  readonly uploadMode = signal<'file' | 'url'>('file');

  readonly form = this.fb.group({
    title: ['', [Validators.required, Validators.minLength(3), Validators.maxLength(200)]],
    url: [''],
  });

  readonly allowedTypes = ['.txt', '.csv', '.md', '.json', '.pdf'];
  readonly maxFileSize = 10 * 1024 * 1024; // 10MB

  setUploadMode(mode: 'file' | 'url'): void {
    this.uploadMode.set(mode);

    if (mode === 'url') {
      this.clearFile();
      this.form.controls.url.setValidators([
        Validators.required,
        Validators.pattern(/^https?:\/\/.+/i),
      ]);
      this.form.controls.url.updateValueAndValidity();

      return;
    }

    this.form.controls.url.clearValidators();
    this.form.controls.url.setValue('');
    this.form.controls.url.updateValueAndValidity();
  }

  isSubmitDisabled(): boolean {
    if (this.isUploading() || this.form.controls.title.invalid) {
      return true;
    }

    if (this.uploadMode() === 'url') {
      return this.form.controls.url.invalid;
    }

    return this.selectedFile() === null;
  }

  /**
   * Handle file selection from input.
   */
  onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    if (input.files?.length) {
      this.validateAndSetFile(input.files[0]);
    }
  }

  /**
   * Handle drag over event.
   */
  onDragOver(event: DragEvent): void {
    event.preventDefault();
    event.stopPropagation();
    this.isDragging.set(true);
  }

  /**
   * Handle drag leave event.
   */
  onDragLeave(event: DragEvent): void {
    event.preventDefault();
    event.stopPropagation();
    this.isDragging.set(false);
  }

  /**
   * Handle file drop.
   */
  onDrop(event: DragEvent): void {
    event.preventDefault();
    event.stopPropagation();
    this.isDragging.set(false);

    if (event.dataTransfer?.files?.length) {
      this.validateAndSetFile(event.dataTransfer.files[0]);
    }
  }

  /**
   * Validate and set selected file.
   */
  private validateAndSetFile(file: File): void {
    const extension = '.' + file.name.split('.').pop()?.toLowerCase();
    if (!this.allowedTypes.includes(extension)) {
      this.toast.error(`Tipo de arquivo não suportado. Use: ${this.allowedTypes.join(', ')}`);
      return;
    }

    if (file.size > this.maxFileSize) {
      this.toast.error('Arquivo muito grande. Máximo: 10MB');
      return;
    }

    this.selectedFile.set(file);

    if (!this.form.get('title')?.value) {
      const titleWithoutExt = file.name.replace(/\.[^/.]+$/, '');
      this.form.patchValue({ title: titleWithoutExt });
    }
  }

  /**
   * Clear selected file.
   */
  clearFile(): void {
    this.selectedFile.set(null);
    if (this.fileInput) {
      this.fileInput.nativeElement.value = '';
    }
  }

  /**
   * Open file browser.
   */
  openFileBrowser(): void {
    this.fileInput.nativeElement.click();
  }

  /**
   * Format file size for display.
   */
  formatFileSize(bytes: number): string {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
  }

  /**
   * Format file extension for display.
   */
  getFileExtension(file: File): string {
    const extension = file.name.split('.').pop()?.toUpperCase();
    return extension ? `.${extension}` : 'N/A';
  }

  /**
   * Format file mime type for display.
   */
  getFileTypeLabel(file: File): string {
    if (file.type.length > 0) {
      return file.type;
    }

    const extension = this.getFileExtension(file);
    return extension !== 'N/A' ? extension : 'Desconhecido';
  }

  /**
   * Format file modified date.
   */
  formatFileDate(timestamp: number): string {
    return new Date(timestamp).toLocaleString('pt-BR');
  }

  /**
   * Submit upload.
   */
  submit(): void {
    if (this.isSubmitDisabled()) return;

    this.isUploading.set(true);
    const title = this.form.get('title')!.value!;

    if (this.uploadMode() === 'url') {
      const url = this.form.controls.url.value?.trim() ?? '';

      this.knowledgeService
        .ingestUrl(url, title)
        .pipe(takeUntilDestroyed(this.destroyRef))
        .subscribe({
          next: () => {
            this.isUploading.set(false);
            this.toast.success('URL enviada. Processamento iniciado.');
            if (this.embedded()) {
              this.resetForm();
              this.saved.emit();
              return;
            }
            void this.router.navigate(['/ai/knowledge/base']);
          },
          error: (error: HttpErrorResponse) => {
            this.isUploading.set(false);
            this.toast.error(this.resolveErrorMessage(error, 'Erro ao ingerir URL.'));
          },
        });

      return;
    }

    const file = this.selectedFile();
    if (!file) {
      this.isUploading.set(false);
      return;
    }

    this.knowledgeService
      .upload(file, title)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.isUploading.set(false);
          this.toast.success('Documento enviado. Processamento iniciado.');
          if (this.embedded()) {
            this.resetForm();
            this.saved.emit();
            return;
          }
          void this.router.navigate(['/ai/knowledge/base']);
        },
        error: (error: HttpErrorResponse) => {
          this.isUploading.set(false);
          this.toast.error(this.resolveErrorMessage(error, 'Erro ao enviar documento.'));
        },
      });
  }

  /**
   * Navigate back to list.
   */
  cancel(): void {
    if (this.embedded()) {
      this.cancelled.emit();
      return;
    }
    void this.router.navigate(['/ai/knowledge/base']);
  }

  private resetForm(): void {
    this.form.reset({ title: '', url: '' });
    this.setUploadMode('file');
    this.clearFile();
  }

  private resolveErrorMessage(error: HttpErrorResponse, fallbackMessage: string): string {
    const apiMessage = error.error?.message;
    if (typeof apiMessage !== 'string' || apiMessage.trim() === '') {
      return fallbackMessage;
    }

    const trimmedMessage = apiMessage.trim();
    if (trimmedMessage.toLowerCase().includes('secured pdf file are currently not supported')) {
      return 'PDF protegido por senha não é suportado. Remova a proteção e tente novamente.';
    }

    return trimmedMessage;
  }
}
