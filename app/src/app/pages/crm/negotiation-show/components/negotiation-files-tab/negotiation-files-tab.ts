import {
  type OnInit,
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  inject,
  input,
  output,
  signal,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { LucideAngularModule } from 'lucide-angular';
import { toast } from 'ngx-sonner';
import {
  type NegotiationFile,
  NegotiationFileService,
} from 'src/app/core/services/negotiation-file.service';
import { AuthStoreService } from 'src/app/core/services/auth-store.service';
import { ButtonComponent, IconButtonComponent } from '@shared/components/buttons';
import { ConfirmModalComponent } from '@shared/components/confirm-modal/confirm-modal';

/**
 * Conteúdo da aba de arquivos — gerencia o CRUD de arquivos da negociação.
 */
@Component({
  selector: 'app-negotiation-files-tab',
  standalone: true,
  imports: [LucideAngularModule, ButtonComponent, IconButtonComponent, ConfirmModalComponent],
  templateUrl: './negotiation-files-tab.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class NegotiationFilesTabComponent implements OnInit {
  private readonly fileService = inject(NegotiationFileService);
  private readonly authStore = inject(AuthStoreService);
  private readonly destroyRef = inject(DestroyRef);

  readonly negotiationId = input.required<string | number>();
  readonly failed = output<string>();

  readonly files = signal<NegotiationFile[]>([]);
  readonly isFilesLoading = signal(false);
  readonly isFileUploading = signal(false);
  readonly deletingFile = signal<NegotiationFile | null>(null);
  readonly downloadingFileId = signal<string | number | null>(null);

  ngOnInit(): void {
    this.loadFiles();
  }

  loadFiles(): void {
    this.isFilesLoading.set(true);
    this.fileService
      .list(this.negotiationId())
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.files.set(response.data.files ?? []);
          this.isFilesLoading.set(false);
        },
        error: () => {
          this.files.set([]);
          this.isFilesLoading.set(false);
          this.failed.emit('Não foi possível carregar os arquivos da negociação.');
        },
      });
  }

  openFilePicker(): void {
    if (this.isFileUploading()) {
      return;
    }

    const input = document.createElement('input');
    input.type = 'file';
    input.accept = '.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.jpg,.jpeg,.png,.gif,.webp';
    input.onchange = () => {
      const file = input.files?.[0];
      if (file) {
        this.uploadFile(file);
      }
    };
    input.click();
  }

  onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement | null;
    const file = input?.files?.[0];
    if (!file) return;

    this.uploadFile(file);

    if (input) {
      input.value = '';
    }
  }

  private uploadFile(file: File): void {
    if (file.size > 5 * 1024 * 1024) {
      toast.error('Arquivo excede o tamanho máximo de 5MB.');
      return;
    }

    this.isFileUploading.set(true);
    this.fileService
      .upload(this.negotiationId(), file)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.isFileUploading.set(false);
          toast.success('Arquivo enviado com sucesso.');
          this.loadFiles();
        },
        error: () => {
          this.isFileUploading.set(false);
          toast.error('Não foi possível enviar o arquivo.');
        },
      });
  }

  confirmDeleteFile(file: NegotiationFile): void {
    this.deletingFile.set(file);
  }

  cancelDeleteFile(): void {
    this.deletingFile.set(null);
  }

  deleteFile(): void {
    const file = this.deletingFile();
    if (!file) return;

    this.fileService
      .delete(this.negotiationId(), file.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          toast.success('Arquivo removido.');
          this.deletingFile.set(null);
          this.loadFiles();
        },
        error: () => {
          this.failed.emit('Não foi possível remover o arquivo.');
        },
      });
  }

  async downloadFile(file: NegotiationFile): Promise<void> {
    if (!file.url) return;
    this.downloadingFileId.set(file.id);
    try {
      const response = await fetch(file.url, { credentials: 'include' });
      if (!response.ok) {
        throw new Error('download-failed');
      }
      const blob = await response.blob();
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = file.original_name || 'arquivo';
      link.click();
      window.URL.revokeObjectURL(url);
    } catch {
      toast.error('Não foi possível baixar o arquivo.');
    } finally {
      this.downloadingFileId.set(null);
    }
  }

  getFileIconName(mimeType?: string | null): string {
    if (!mimeType) return 'file';
    if (mimeType.startsWith('image/')) return 'image';
    if (mimeType === 'application/pdf') return 'file-text';
    return 'file';
  }

  formatDateTime(value?: string | null): string {
    if (!value) return '-';
    const date = new Date(value);
    return Number.isNaN(date.getTime())
      ? '-'
      : date.toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
  }

  getUploaderLabel(file: NegotiationFile): string {
    if (file.user?.name) {
      return file.user.name;
    }

    if (file.user_id) {
      const currentUser = this.authStore.user();
      if (currentUser && String(currentUser.id) === String(file.user_id)) {
        return currentUser.name;
      }

      return `Usuário ${String(file.user_id).slice(0, 8)}`;
    }

    return '-';
  }
}
