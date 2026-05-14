import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  ElementRef,
  HostListener,
  inject,
  input,
  output,
  signal,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { LucideAngularModule } from 'lucide-angular';
import { AfButtonComponent } from '@shared/components';
import {
  PlatformLeadService,
  type PlatformLeadFilters,
} from '@core/services/platform-lead.service';

@Component({
  selector: 'app-lead-export-button',
  standalone: true,
  imports: [ReactiveFormsModule, LucideAngularModule, AfButtonComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './lead-export-button.html',
})
export class LeadExportButtonComponent {
  private readonly service = inject(PlatformLeadService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly elementRef = inject(ElementRef<HTMLElement>);

  readonly searchTerm = input<string>('');

  readonly exported = output<void>();
  readonly errorOccurred = output<string>();

  readonly isOpen = signal(false);
  readonly isExporting = signal(false);

  readonly exportSearchControl = new FormControl<string>('', { nonNullable: true });

  toggleOpen(): void {
    const next = !this.isOpen();
    this.isOpen.set(next);

    if (!next) {
      return;
    }

    this.exportSearchControl.setValue(this.searchTerm(), { emitEvent: false });
  }

  exportCsv(): void {
    if (this.isExporting()) {
      return;
    }

    const filters: PlatformLeadFilters = {
      search: this.exportSearchControl.value || undefined,
      sort_by: 'created_at',
      sort_dir: 'desc',
    };

    this.isExporting.set(true);

    this.service
      .export(filters)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (blob) => {
          this.isExporting.set(false);
          const fileName = `platform_leads_${new Date().toISOString().slice(0, 10)}.csv`;

          const url = window.URL.createObjectURL(blob);
          const anchor = document.createElement('a');
          anchor.href = url;
          anchor.download = fileName;
          document.body.appendChild(anchor);
          anchor.click();
          document.body.removeChild(anchor);
          window.URL.revokeObjectURL(url);

          this.exported.emit();
          this.isOpen.set(false);
        },
        error: () => {
          this.isExporting.set(false);
          this.errorOccurred.emit('Não foi possível exportar os leads.');
        },
      });
  }

  @HostListener('document:click', ['$event'])
  onDocumentClick(event: MouseEvent): void {
    if (!this.isOpen()) {
      return;
    }

    if (!this.elementRef.nativeElement.contains(event.target as Node)) {
      this.isOpen.set(false);
    }
  }
}
