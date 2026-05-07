import { ChangeDetectionStrategy, Component, DestroyRef, computed, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfAlertComponent,
  AfButtonComponent,
  AfCrudPageComponent,
  AfDataTableComponent,
  AfSelectInputComponent,
  type AfSelectOption,
} from '@shared/components';
import {
  type PlatformLead,
  PlatformLeadService,
  type PlatformLeadFilters,
} from '@core/services/platform-lead.service';

@Component({
  selector: 'app-platform-leads',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    LucideAngularModule,
    AfCrudPageComponent,
    AfDataTableComponent,
    AfSelectInputComponent,
    AfAlertComponent,
    AfButtonComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './platform-leads.html',
})
export class PlatformLeads {
  private readonly service = inject(PlatformLeadService);
  private readonly destroyRef = inject(DestroyRef);

  readonly leads = signal<PlatformLead[]>([]);
  readonly isLoading = signal(false);
  readonly hasError = signal(false);
  readonly currentSearchTerm = signal('');
  readonly meta = signal({ current_page: 1, last_page: 1, per_page: 15, total: 0 });

  readonly statusFilterControl = new FormControl<string>('all', { nonNullable: true });
  readonly statusFilterOptions: AfSelectOption[] = [
    { label: 'Todos os status', value: 'all' },
    { label: 'Novo', value: 'new' },
    { label: 'Contatado', value: 'contacted' },
    { label: 'Qualificado', value: 'qualified' },
    { label: 'Convertido', value: 'converted' },
    { label: 'Perdido', value: 'lost' },
  ];

  readonly sourceFilterControl = new FormControl<string>('all', { nonNullable: true });
  readonly sourceFilterOptions: AfSelectOption[] = [
    { label: 'Todas as origens', value: 'all' },
    { label: 'Landing Form', value: 'landing_form' },
    { label: 'Exit Modal', value: 'landing_exit_modal' },
  ];

  readonly isEmpty = computed(
    () => !this.isLoading() && !this.hasError() && this.leads().length === 0,
  );

  private page = 1;

  constructor() {
    this.statusFilterControl.valueChanges.pipe(takeUntilDestroyed(this.destroyRef)).subscribe(() => {
      this.page = 1;
      this.loadLeads();
    });

    this.sourceFilterControl.valueChanges.pipe(takeUntilDestroyed(this.destroyRef)).subscribe(() => {
      this.page = 1;
      this.loadLeads();
    });

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

  statusLabel(status: string): string {
    const map: Record<string, string> = {
      new: 'Novo',
      contacted: 'Contatado',
      qualified: 'Qualificado',
      converted: 'Convertido',
      lost: 'Perdido',
    };

    return map[status] ?? status;
  }

  formatPhone(phone: string | null | undefined): string {
    if (!phone) return '—';

    const digits = phone.replace(/\D/g, '');
    if (digits.length === 11) {
      return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
    }

    if (digits.length === 10) {
      return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`;
    }

    return phone;
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

    const status = this.statusFilterControl.value;
    if (status !== 'all') filters.status = status;

    const source = this.sourceFilterControl.value;
    if (source !== 'all') filters.source = source;

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
