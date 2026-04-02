import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  inject,
  input,
  signal,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { LucideAngularModule } from 'lucide-angular';
import { AfButtonComponent, AfLoadingButtonComponent } from '@shared/components';
import { FormBuilder, ReactiveFormsModule } from '@angular/forms';
import { ToastService } from '@core/services/toast.service';
import { type AiAgentFile } from '@ai/models/ai.model';
import { AiAgentService } from '@ai/services/ai-agent.service';

/**
 * Files tab — split layout with file list on left and editor on right.
 * Follows the OpenClaw reference layout for agent core files management.
 */
@Component({
  selector: 'app-agent-files-tab',
  standalone: true,
  imports: [ReactiveFormsModule, LucideAngularModule, AfButtonComponent, AfLoadingButtonComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './files-tab.html',
})
export class AgentFilesTabComponent {
  private readonly agentService = inject(AiAgentService);
  private readonly toast = inject(ToastService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly fb = inject(FormBuilder);

  readonly agentId = input.required<string>();

  readonly files = signal<AiAgentFile[]>([]);
  readonly loading = signal(false);
  readonly error = signal<string | null>(null);
  readonly selectedFile = signal<AiAgentFile | null>(null);
  readonly saving = signal(false);
  readonly uploading = signal(false);
  readonly editorControl = this.fb.control('');

  constructor() {
    setTimeout(() => this.loadFiles(), 0);
  }

  /**
   * Load files for the agent.
   */
  loadFiles(): void {
    const id = this.agentId();
    if (!id) return;

    this.loading.set(true);
    this.error.set(null);

    this.agentService
      .getFiles(id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (files) => {
          this.files.set(files);
          this.loading.set(false);

          // Auto-select first file if none selected
          if (this.selectedFile() === null && files.length > 0) {
            this.selectFile(files[0]);
          }
        },
        error: () => {
          this.error.set('Erro ao carregar arquivos.');
          this.loading.set(false);
        },
      });
  }

  /**
   * Select a file and load its content.
   */
  selectFile(file: AiAgentFile): void {
    this.agentService
      .getFile(this.agentId(), file.slug)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (loadedFile) => {
          this.selectedFile.set(loadedFile);
          this.editorControl.setValue(loadedFile.content);
        },
        error: () => {
          this.toast.error('Erro ao carregar arquivo.');
        },
      });
  }

  /**
   * Reset editor content to the original file value.
   */
  resetContent(): void {
    const file = this.selectedFile();
    if (file !== null) {
      this.editorControl.setValue(file.content);
    }
  }

  /**
   * Save the current file being edited.
   */
  saveFile(): void {
    const file = this.selectedFile();
    if (file === null) return;

    this.saving.set(true);

    this.agentService
      .updateFile(this.agentId(), file.slug, this.editorControl.value ?? '')
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (updated) => {
          this.saving.set(false);
          this.selectedFile.set(updated);
          this.toast.success('Arquivo salvo.');
          this.loadFiles();
        },
        error: () => {
          this.saving.set(false);
          this.toast.error('Erro ao salvar arquivo.');
        },
      });
  }

  /**
   * Format a date string to relative time.
   */
  formatRelativeTime(dateStr: string): string {
    const date = new Date(dateStr);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return 'agora';
    if (diffMins < 60) return `${diffMins}m atrás`;
    if (diffHours < 24) return `${diffHours}h atrás`;
    return `${diffDays}d atrás`;
  }

  /**
   * Import one or more local files (.md/.txt) and upload as agent files.
   */
  onFilesSelected(event: Event): void {
    const target = event.target as HTMLInputElement;
    const selectedFiles = target.files;
    if (selectedFiles === null || selectedFiles.length === 0) {
      return;
    }

    const files = Array.from(selectedFiles);
    this.uploading.set(true);

    Promise.all(files.map((file) => this.uploadSingleFile(file)))
      .then(() => {
        this.toast.success('Arquivos importados com sucesso.');
        this.loadFiles();
      })
      .catch(() => {
        this.toast.error('Erro ao importar um ou mais arquivos.');
      })
      .finally(() => {
        this.uploading.set(false);
        target.value = '';
      });
  }

  private async uploadSingleFile(file: File): Promise<void> {
    const content = await this.readFileAsText(file);
    const slug = this.normalizeSlug(file.name);

    await new Promise<void>((resolve, reject) => {
      this.agentService
        .updateFile(this.agentId(), slug, content)
        .pipe(takeUntilDestroyed(this.destroyRef))
        .subscribe({
          next: () => resolve(),
          error: () => reject(new Error('upload-failed')),
        });
    });
  }

  private readFileAsText(file: File): Promise<string> {
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = () => resolve((reader.result as string) ?? '');
      reader.onerror = () => reject(new Error('read-failed'));
      reader.readAsText(file);
    });
  }

  private normalizeSlug(fileName: string): string {
    const trimmed = fileName.trim();
    if (trimmed === '') {
      return 'UNTITLED.md';
    }

    const hasExtension = /\.[A-Za-z0-9]+$/.test(trimmed);
    const withExtension = hasExtension ? trimmed : `${trimmed}.md`;
    return withExtension.replace(/\s+/g, '_');
  }
}
