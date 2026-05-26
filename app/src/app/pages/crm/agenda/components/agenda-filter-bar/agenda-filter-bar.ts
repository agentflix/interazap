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





/**
 * Barra de filtros da agenda com alternância de visualização, chips de filtros ativos e exportação.
 */
@Component({
  selector: 'app-agenda-filter-bar',
  standalone: true,
  imports: [
    LucideAngularModule,
    ButtonComponent,
    AfReportFiltersComponent,
    AfReportExportComponent,
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

  /** Emite o modo de visualização selecionado (lista ou calendário). */
  onChangeView(mode: AgendaViewMode): void {
    this.viewChanged.emit(mode);
  }

  /** Emite o evento de clique no botão de criar. */
  onCreate(): void {
    this.createClicked.emit();
  }

  /** Emite o evento de alternância do painel de filtros avançados. */
  onToggleAdvanced(): void {
    this.toggleAdvancedClicked.emit();
  }

  /**
   * Emite a chave do chip de filtro a ser removido.
   * @param key Identificador do filtro a remover
   */
  onRemoveChip(key: string): void {
    this.removeChipClicked.emit(key);
  }

  /**
   * Repassa o payload de filtros aplicados ao componente pai.
   * @param payload Filtros selecionados pelo usuário
   */
  handleFiltersApplied(payload: AfReportFilterPayload): void {
    this.filtersApplied.emit(payload);
  }

  /**
   * Repassa o payload de exportação ao componente pai.
   * @param payload Configuração de exportação selecionada
   */
  handleExport(payload: AfReportExportPayload): void {
    this.exported.emit(payload);
  }
}
