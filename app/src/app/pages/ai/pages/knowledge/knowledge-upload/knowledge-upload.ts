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
 * Upload de documentos para a base de conhecimento da IA.
 *
 * Contexto: suporta dois modos: upload de arquivo (drag-and-drop ou seleção) e ingestão
 * por URL. Tipos permitidos: .txt, .csv, .md, .json, .pdf (máx. 10MB). PDFs protegidos
 * por senha são rejeitados com mensagem amigável. Pode ser usado embutido em modal ou
 * como página independente.
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

  /**
   * Alterna entre modo de upload de arquivo e modo de ingestão por URL.
   * @param mode 'file' para arquivo local, 'url' para URL pública
   */
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

  /**
   * Verifica se o botão de envio deve estar desabilitado.
   * @returns true se o formulário é inválido ou o envio está em andamento
   */
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
   * Trata a seleção de arquivo via input.
   * @param event Evento de seleção do input
   */
  onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    if (input.files?.length) {
      this.validateAndSetFile(input.files[0]);
    }
  }

  /**
   * Trata o evento de drag-over na área de drop.
   * @param event Evento de drag
   */
  onDragOver(event: DragEvent): void {
    event.preventDefault();
    event.stopPropagation();
    this.isDragging.set(true);
  }

  /**
   * Trata o evento de saída da área de drop.
   * @param event Evento de drag
   */
  onDragLeave(event: DragEvent): void {
    event.preventDefault();
    event.stopPropagation();
    this.isDragging.set(false);
  }

  /**
   * Trata o drop de arquivo na área designada.
   * @param event Evento de drop
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
   * Valida e define o arquivo selecionado verificando tipo e tamanho.
   * @param file Arquivo a ser validado
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
   * Limpa o arquivo selecionado e reseta o input.
   */
  clearFile(): void {
    this.selectedFile.set(null);
    if (this.fileInput) {
      this.fileInput.nativeElement.value = '';
    }
  }

  /**
   * Abre o seletor de arquivos do sistema operacional.
   */
  openFileBrowser(): void {
    this.fileInput.nativeElement.click();
  }

  /**
   * Formata o tamanho do arquivo para exibição (B, KB, MB).
   * @param bytes Tamanho em bytes
   * @returns String formatada
   */
  formatFileSize(bytes: number): string {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
  }

  /**
   * Retorna a extensão do arquivo em maiúsculas para exibição.
   * @param file Arquivo selecionado
   * @returns Extensão (ex.: ".PDF") ou "N/A"
   */
  getFileExtension(file: File): string {
    const extension = file.name.split('.').pop()?.toUpperCase();
    return extension ? `.${extension}` : 'N/A';
  }

  /**
   * Retorna o tipo MIME ou extensão do arquivo para exibição.
   * @param file Arquivo selecionado
   * @returns Tipo MIME ou extensão como fallback
   */
  getFileTypeLabel(file: File): string {
    if (file.type.length > 0) {
      return file.type;
    }

    const extension = this.getFileExtension(file);
    return extension !== 'N/A' ? extension : 'Desconhecido';
  }

  /**
   * Formata a data de modificação do arquivo.
   * @param timestamp Timestamp em milissegundos
   * @returns String de data localizada em pt-BR
   */
  formatFileDate(timestamp: number): string {
    return new Date(timestamp).toLocaleString('pt-BR');
  }

  /**
   * Envia o formulário para criar a entrada na base de conhecimento (arquivo ou URL).
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
   * Cancela a operação e navega de volta para a lista (ou emite evento quando embutido).
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
