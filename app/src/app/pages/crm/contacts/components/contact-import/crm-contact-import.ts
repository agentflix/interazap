import {
  ChangeDetectionStrategy,
  Component,
  computed,
  DestroyRef,
  inject,
  output,
  signal,
} from '@angular/core';
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { startWith } from 'rxjs';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfButtonComponent,
  AfFileInputComponent,
  AfLoadingButtonComponent,
  AfSelectInputComponent,
  type AfSelectOption,
} from '@shared/components';
import { ContactService } from '@core/services/crm-contact.service';
import type { ContactImportSummary } from '@core/models/contact.model';

/**
 * Componente de importação de contatos via wizard de 3 etapas:
 * upload do arquivo → mapeamento de colunas → resumo da importação.
 */
@Component({
  selector: 'app-contact-import',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    LucideAngularModule,
    AfButtonComponent,
    AfLoadingButtonComponent,
    AfSelectInputComponent,
    AfFileInputComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './crm-contact-import.html',
})
export class ContactImportComponent {
  private readonly contactService = inject(ContactService);
  private readonly destroyRef = inject(DestroyRef);

  /**
   * Evento emitido quando o resumo da importação está disponível.
   */
  readonly imported = output<ContactImportSummary>();
  /**
   * Evento emitido quando ocorre um erro durante o upload ou importação.
   */
  readonly errorOccurred = output<string>();

  /**
   * Etapa atual do wizard de importação.
   */
  readonly step = signal<'upload' | 'mapping' | 'summary'>('upload');
  /**
   * Arquivo CSV selecionado.
   */
  readonly file = signal<File | null>(null);
  /**
   * Controle para o input de arquivo.
   */
  readonly fileControl = new FormControl<FileList | null>(null);
  /**
   * Cabeçalhos do CSV extraídos do arquivo.
   */
  readonly headers = signal<string[]>([]);
  /**
   * Linhas de exemplo para pré-visualização.
   */
  readonly sample = signal<string[][]>([]);
  /**
   * Separador CSV usado no arquivo.
   */
  readonly delimiter = signal<',' | ';'>(',');
  /**
   * ID único do processo de importação atual.
   */
  readonly importId = signal<string>('');
  /**
   * Resumo dos resultados da importação.
   */
  readonly summary = signal<ContactImportSummary | null>(null);
  /**
   * Estado de carregamento para chamadas à API.
   */
  readonly isLoading = signal(false);

  /**
   * Controles de formulário para mapear cabeçalhos do CSV para campos de contato.
   */
  readonly mappingControls = {
    name: new FormControl('', { nonNullable: true }),
    number: new FormControl('', { nonNullable: true }),
    email: new FormControl('', { nonNullable: true }),
    company: new FormControl('', { nonNullable: true }),
  };

  /**
   * Opções para os selects de mapeamento baseadas nos cabeçalhos do CSV.
   */
  readonly headerOptions = computed<AfSelectOption[]>(() =>
    this.headers().map((header) => ({ label: header, value: header })),
  );

  /**
   * Configuração atual do mapeamento.
   */
  readonly mapping = signal<{
    name: string;
    number: string;
    email?: string;
    company?: string;
  }>({ name: '', number: '', email: '', company: '' });

  /**
   * Contagem de números de telefone inválidos na amostra de pré-visualização.
   */
  readonly invalidSampleCount = computed(() => this.countInvalidSampleNumbers());

  constructor() {
    this.setupMappingControls();
    this.setupFileControl();
  }

  /**
   * Sinal que indica a etapa atual do processo de importação.
   */
  onFileSelected(files: FileList | null): void {
    const file = files?.[0] ?? null;
    if (!file) return;

    this.file.set(file);
    this.isLoading.set(true);

    this.contactService
      .uploadImport(file)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.headers.set(response.data.headers ?? []);
          this.sample.set(response.data.sample ?? []);
          this.delimiter.set(response.data.delimiter ?? ',');
          this.importId.set(response.data.import_id ?? '');
          this.step.set('mapping');
          this.isLoading.set(false);
        },
        error: () => {
          this.isLoading.set(false);
          this.errorOccurred.emit('Falha ao enviar o arquivo.');
        },
      });
  }

  /**
   * Evento emitido quando a importação é cancelada.
   */
  startImport(): void {
    const importId = this.importId();
    if (!importId) {
      this.errorOccurred.emit('Identificador de importação inválido.');
      return;
    }
    if (!this.mapping().name || !this.mapping().number) {
      this.errorOccurred.emit('Mapeie os campos Nome e Número.');
      return;
    }
    if (this.invalidSampleCount() > 0) {
      this.errorOccurred.emit('Corrija os números inválidos antes de continuar.');
      return;
    }

    this.isLoading.set(true);

    this.contactService
      .importContacts({
        import_id: importId,
        delimiter: this.delimiter(),
        has_header: true,
        mapping: this.mapping(),
      })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.summary.set(response.data);
          this.step.set('summary');
          this.isLoading.set(false);
          this.imported.emit(response.data);
        },
        error: () => {
          this.isLoading.set(false);
          this.errorOccurred.emit('Falha ao importar contatos.');
        },
      });
  }

  /**
   * Baixa um arquivo CSV de exemplo como modelo para os usuários.
   */
  downloadTemplate(): void {
    const headers = ['name', 'number', 'email', 'company'];
    const sampleRow = ['Maria Silva', '5511999999999', 'maria@email.com', 'Empresa XPTO'];
    const content = `${headers.join(',')}\n${sampleRow.join(',')}\n`;
    const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
    const url = window.URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = 'contacts_import_template.csv';
    document.body.appendChild(anchor);
    anchor.click();
    document.body.removeChild(anchor);
    window.URL.revokeObjectURL(url);
  }

  /**
   * Reseta o estado do componente para a etapa inicial de upload.
   */
  reset(): void {
    this.step.set('upload');
    this.file.set(null);
    this.fileControl.setValue(null, { emitEvent: false });
    this.headers.set([]);
    this.sample.set([]);
    this.delimiter.set(',');
    this.importId.set('');
    this.summary.set(null);
    this.mapping.set({ name: '', number: '', email: '', company: '' });
    this.mappingControls.name.setValue('', { emitEvent: false });
    this.mappingControls.number.setValue('', { emitEvent: false });
    this.mappingControls.email.setValue('', { emitEvent: false });
    this.mappingControls.company.setValue('', { emitEvent: false });
  }

  private setupMappingControls(): void {
    this.mappingControls.name.valueChanges
      .pipe(startWith(this.mappingControls.name.value), takeUntilDestroyed(this.destroyRef))
      .subscribe((value) => this.updateMapping('name', value));
    this.mappingControls.number.valueChanges
      .pipe(startWith(this.mappingControls.number.value), takeUntilDestroyed(this.destroyRef))
      .subscribe((value) => this.updateMapping('number', value));
    this.mappingControls.email.valueChanges
      .pipe(startWith(this.mappingControls.email.value), takeUntilDestroyed(this.destroyRef))
      .subscribe((value) => this.updateMapping('email', value));
    this.mappingControls.company.valueChanges
      .pipe(startWith(this.mappingControls.company.value), takeUntilDestroyed(this.destroyRef))
      .subscribe((value) => this.updateMapping('company', value));
  }

  private setupFileControl(): void {
    this.fileControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((files) => this.onFileSelected(files));
  }

  private updateMapping(field: 'name' | 'number' | 'email' | 'company', value: string): void {
    const current = this.mapping();
    this.mapping.set({ ...current, [field]: value });
  }

  private countInvalidSampleNumbers(): number {
    const mapping = this.mapping().number;
    const hdr = this.headers();
    if (!mapping || hdr.length === 0) return 0;
    const index = hdr.findIndex((h) => h === mapping);
    if (index < 0) return 0;
    return this.sample().reduce((acc, row) => {
      const value = row?.[index] ?? '';
      const digits = value.replace(/\D/g, '');
      return acc + (digits.length >= 10 && digits.length <= 15 ? 0 : 1);
    }, 0);
  }
}
