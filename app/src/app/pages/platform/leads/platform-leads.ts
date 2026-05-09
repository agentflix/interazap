import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  inject,
  signal,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ReactiveFormsModule } from '@angular/forms';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfAlertComponent,
  AfButtonComponent,
  AfCrudPageComponent,
  AfDataTableComponent,
} from '@shared/components';
import { ToastService } from '@core/services/toast.service';
import { UtilsService } from '@core/services/utils.service';
import {
  type PlatformLead,
  PlatformLeadService,
  type PlatformLeadFilters,
} from '@core/services/platform-lead.service';
import { LeadConvertModalComponent } from './components/lead-convert-modal/lead-convert-modal';
import { LeadExportButtonComponent } from './components/lead-export-button/lead-export-button';

@Component({
  selector: 'app-platform-leads',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    LucideAngularModule,
    AfCrudPageComponent,
    AfDataTableComponent,
    AfAlertComponent,
    AfButtonComponent,
    LeadConvertModalComponent,
    LeadExportButtonComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './platform-leads.html',
})
export class PlatformLeads {
  private readonly service = inject(PlatformLeadService);
  private readonly toast = inject(ToastService);
  private readonly utils = inject(UtilsService);
  private readonly destroyRef = inject(DestroyRef);

  readonly leads = signal<PlatformLead[]>([]);
  readonly isLoading = signal(false);
  readonly hasError = signal(false);
  readonly currentSearchTerm = signal('');
  readonly meta = signal({ current_page: 1, last_page: 1, per_page: 15, total: 0 });
  readonly showConvertModal = signal(false);
  readonly selectedLead = signal<PlatformLead | null>(null);

  readonly isEmpty = computed(
    () => !this.isLoading() && !this.hasError() && this.leads().length === 0,
  );

  private page = 1;

  constructor() {
    this.loadLeads();
  }

  onSearch(term: string): void {
    this.currentSearchTerm.set(term);
    this.page = 1;
    this.loadLeads();
  }

  loadPage(page: number): void {
    this.page = page;
    this.loadLeads();
  }

  retry(): void {
    this.loadLeads();
  }

  openConvertModal(lead: PlatformLead): void {
    this.selectedLead.set(lead);
    this.showConvertModal.set(true);
  }

  closeConvertModal(): void {
    this.showConvertModal.set(false);
    this.selectedLead.set(null);
  }

  onConvertedLead(): void {
    this.toast.success('Lead convertido com sucesso.');
    this.closeConvertModal();
    this.loadLeads();
  }

  handleExportError(message: string): void {
    this.toast.error(message);
  }

  handleExported(): void {
    this.toast.success('Arquivo CSV gerado com sucesso.');
  }

  formatPhone(phone: string | null | undefined): string {
    return this.utils.formatPhone(phone ?? undefined);
  }

  private loadLeads(): void {
    this.isLoading.set(true);
    this.hasError.set(false);

    const filters: PlatformLeadFilters = {
      search: this.currentSearchTerm(),
      page: this.page,
      per_page: this.meta().per_page,
      sort_by: 'created_at',
      sort_dir: 'desc',
    };

    this.service
      .list(filters)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.leads.set(response.data);
          this.meta.set(response.meta);
          this.isLoading.set(false);
        },
        error: () => {
          this.hasError.set(true);
          this.isLoading.set(false);
        },
      });
  }
}
