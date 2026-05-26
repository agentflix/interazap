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
 * Componente de exportação de contatos — dispara o download do arquivo CSV
 * com base nos filtros ativos na listagem.
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

  /**
   * Termo de busca atual para filtrar a exportação.
   */
  readonly searchTerm = input<string>('');
  /**
   * Filtro de status atual para filtrar a exportação.
   */
  readonly statusFilter = input<string>('all');
  /**
   * Quando true, renderiza apenas o ícone sem texto.
   */
  readonly iconOnly = input(false);

  /**
   * Evento emitido após uma exportação bem-sucedida.
   */
  readonly exported = output<void>();
  /**
   * Evento emitido quando a exportação falha.
   */
  readonly errorOccurred = output<string>();

  /**
   * Indica se o processo de exportação está em andamento.
   */
  readonly isExporting = signal(false);

  /**
   * Cancela a exportação em andamento.
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
