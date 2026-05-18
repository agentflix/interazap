import {
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
import { AfButtonComponent } from '@shared/components';
import { ContactService } from '@core/services/crm-contact.service';
import type { ContactFilters } from '@core/models/contact.model';

/**
 * Contact export component — triggers CSV download.
 * Business logic preserved verbatim from source. Visual layer migrated to UI Kit.
 */
@Component({
  selector: 'app-contact-export',
  standalone: true,
  imports: [LucideAngularModule, AfButtonComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './crm-contact-export.html',
})
export class ContactExportComponent {
  private readonly contactService = inject(ContactService);
  private readonly destroyRef = inject(DestroyRef);

  /** Current search term to filter export */
  readonly searchTerm = input<string>('');
  /** Current status filter to filter export */
  readonly statusFilter = input<string>('all');
  /** When true, renders only the icon with no label text */
  readonly iconOnly = input(false);

  /** Emitted after a successful export */
  readonly exported = output<void>();
  /** Emitted when export fails */
  readonly errorOccurred = output<string>();

  /** Whether the export process is in progress */
  readonly isExporting = signal(false);

  /**
   * Triggers the contact export process.
   * Downloads a CSV file with filtered contact data.
   */
  exportContacts(): void {
    const status = this.statusFilter();
    const statusValue = status === 'active' ? true : status === 'inactive' ? false : undefined;

    const params: ContactFilters = {
      search: this.searchTerm() || undefined,
      is_active: statusValue,
    };

    this.isExporting.set(true);

    this.contactService
      .export(params)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (blob) => {
          this.isExporting.set(false);
          const url = window.URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          a.download = `contacts_${new Date().toISOString().split('T')[0]}.csv`;
          document.body.appendChild(a);
          a.click();
          document.body.removeChild(a);
          window.URL.revokeObjectURL(url);
          this.exported.emit();
        },
        error: () => {
          this.isExporting.set(false);
          this.errorOccurred.emit('Não foi possível exportar os contatos.');
        },
      });
  }
}
