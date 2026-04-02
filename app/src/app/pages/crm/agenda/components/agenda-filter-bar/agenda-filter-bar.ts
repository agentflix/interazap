import { ChangeDetectionStrategy, Component, input, output } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';
import { ButtonComponent } from '@shared/components/buttons';
import {
  AfReportFiltersComponent,
  type AfReportFilterPayload,
} from '@shared/components/report-filters/report-filters';
import {
  AfReportExportComponent,
  type AfReportExportPayload,
} from '@shared/components/report-export/report-export';
import { type AfSelectOption } from '@shared/components/select-input/select-input';

import type { AgendaViewMode, AgendaActiveFilterChip } from './agenda-filter-bar.model';
export * from './agenda-filter-bar.model';





@Component({
  selector: 'app-agenda-filter-bar',
  standalone: true,
  imports: [
    LucideAngularModule,
    ButtonComponent,
    AfReportFiltersComponent,
    AfReportExportComponent,
/**
 * Agenda filter bar component for the Crm module.
 * @selector app-agenda-filter-bar
 */
  ],
  templateUrl: './agenda-filter-bar.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AgendaFilterBarComponent {
  readonly mode = input.required<AgendaViewMode>();
  readonly statusOptions = input<AfSelectOption[]>([]);

  readonly activeChips = input<AgendaActiveFilterChip[]>([]);
  readonly showCreateButton = input(true);
  readonly advancedCount = input(0);

  readonly viewChanged = output<AgendaViewMode>();
  readonly createClicked = output<void>();
  readonly removeChipClicked = output<string>();
  readonly toggleAdvancedClicked = output<void>();
  readonly filtersApplied = output<AfReportFilterPayload>();
  readonly exported = output<AfReportExportPayload>();

  onChangeView(mode: AgendaViewMode): void {
    this.viewChanged.emit(mode);
  }

  onCreate(): void {
    this.createClicked.emit();
  }

  onToggleAdvanced(): void {
    this.toggleAdvancedClicked.emit();
  }

  onRemoveChip(key: string): void {
    this.removeChipClicked.emit(key);
  }

  handleFiltersApplied(payload: AfReportFilterPayload): void {
    this.filtersApplied.emit(payload);
  }

  handleExport(payload: AfReportExportPayload): void {
    this.exported.emit(payload);
  }
}
