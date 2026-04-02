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
import { type ContactImportSummary, ContactService } from '@core/services/crm-contact.service';

/**
 * Contact import component — 3-step wizard: upload → mapping → summary.
 * Business logic preserved verbatim from source. Visual layer migrated to UI Kit.
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

  /** Emitted when import summary is available */
  readonly imported = output<ContactImportSummary>();
  /** Emitted when an error occurs during upload or import */
  readonly errorOccurred = output<string>();

  /** Current step in the import wizard */
  readonly step = signal<'upload' | 'mapping' | 'summary'>('upload');
  /** The selected CSV file */
  readonly file = signal<File | null>(null);
  /** Control for the file input */
  readonly fileControl = new FormControl<FileList | null>(null);
  /** CSV headers extracted from the file */
  readonly headers = signal<string[]>([]);
  /** Sample rows for preview */
  readonly sample = signal<string[][]>([]);
  /** CSV delimiter used in the file */
  readonly delimiter = signal<',' | ';'>(',');
  /** Unique ID for the current import process */
  readonly importId = signal<string>('');
  /** Summary of the import results */
  readonly summary = signal<ContactImportSummary | null>(null);
  /** Loading state for API calls */
  readonly isLoading = signal(false);

  /** Form controls for mapping CSV headers to contact fields */
  readonly mappingControls = {
    name: new FormControl('', { nonNullable: true }),
    number: new FormControl('', { nonNullable: true }),
    email: new FormControl('', { nonNullable: true }),
    company: new FormControl('', { nonNullable: true }),
  };

  /** Options for the mapping select inputs based on CSV headers */
  readonly headerOptions = computed<AfSelectOption[]>(() =>
    this.headers().map((header) => ({ label: header, value: header })),
  );

  /** Current mapping configuration */
  readonly mapping = signal<{
    name: string;
    number: string;
    email?: string;
    company?: string;
  }>({ name: '', number: '', email: '', company: '' });

  /** Count of invalid phone numbers in the preview sample */
  readonly invalidSampleCount = computed(() => this.countInvalidSampleNumbers());

  constructor() {
    this.setupMappingControls();
    this.setupFileControl();
  }

  /**
   * Handles file selection and uploads the file for initial processing.
   * @param files - List of selected files.
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
   * Starts the actual contact import process using the current mapping.
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
   * Downloads a sample CSV template for users to follow.
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
   * Resets the component state to the initial upload step.
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
