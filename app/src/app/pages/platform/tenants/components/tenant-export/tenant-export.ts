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
import { CompanyService } from '@core/services/company.service';

/**
 * Botão de exportação de tenants — gera arquivo CSV com os tenants filtrados.
 */
@Component({
  selector: 'app-tenant-export',
  standalone: true,
  imports: [LucideAngularModule, AfButtonComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './tenant-export.html',
})
export class TenantExportComponent {
  private readonly companyService = inject(CompanyService);
  private readonly destroyRef = inject(DestroyRef);

  readonly searchTerm = input<string>('');
  readonly statusFilter = input<string>('all');

  readonly exported = output<void>();
  readonly errorOccurred = output<string>();

  readonly isExporting = signal(false);

  exportTenants(): void {
    const status = this.statusFilter();
    const isActive = status === 'active' ? true : status === 'inactive' ? false : undefined;

    this.isExporting.set(true);

    this.companyService
      .export({
        search: this.searchTerm() || undefined,
        is_active: isActive,
        trashed: status === 'trashed',
      })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.isExporting.set(false);

          const blob = response.body;
          if (!blob) {
            this.errorOccurred.emit('Não foi possível gerar o arquivo CSV.');
            return;
          }

          const filename = this.extractFileName(response.headers.get('content-disposition'));
          const url = window.URL.createObjectURL(blob);
          const anchor = document.createElement('a');
          anchor.href = url;
          anchor.download = filename;
          document.body.appendChild(anchor);
          anchor.click();
          document.body.removeChild(anchor);
          window.URL.revokeObjectURL(url);
          this.exported.emit();
        },
        error: () => {
          this.isExporting.set(false);
          this.errorOccurred.emit('Não foi possível exportar os tenants.');
        },
      });
  }

  private extractFileName(contentDisposition: string | null): string {
    if (!contentDisposition) {
      return `tenants_export_${new Date().toISOString().slice(0, 10).replace(/-/g, '')}.csv`;
    }

    const utf8Match = contentDisposition.match(/filename\*=UTF-8''([^;]+)/i);
    if (utf8Match && utf8Match[1]) {
      return decodeURIComponent(utf8Match[1]);
    }

    const fallbackMatch = contentDisposition.match(/filename="?([^";]+)"?/i);
    if (fallbackMatch && fallbackMatch[1]) {
      return fallbackMatch[1];
    }

    return `tenants_export_${new Date().toISOString().slice(0, 10).replace(/-/g, '')}.csv`;
  }
}
