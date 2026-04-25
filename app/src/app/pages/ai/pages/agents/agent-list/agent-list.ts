import { ChangeDetectionStrategy, Component, DestroyRef, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Router } from '@angular/router';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfAlertComponent,
  AfButtonComponent,
  AfConfirmModalComponent,
  AfCrudPageComponent,
  AfDataTableComponent,
  AfIconButtonComponent,
  AfStatusBadgeComponent,
} from '@shared/components';
import { ToastService } from '@core/services/toast.service';
import { type AiAgent } from '@ai/models/ai.model';
import { AiAgentService } from '@ai/services/ai-agent.service';

/**
 * List and manage AI agents (V2 — workspace layout).
 */
@Component({
  selector: 'app-ai-agent-list',
  standalone: true,
  imports: [
    LucideAngularModule,
    AfCrudPageComponent,
    AfDataTableComponent,
    AfButtonComponent,
    AfIconButtonComponent,
    AfAlertComponent,
    AfStatusBadgeComponent,
    AfConfirmModalComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './agent-list.html',
})
export class AgentListComponent {
  private readonly agentService = inject(AiAgentService);
  private readonly router = inject(Router);
  private readonly toast = inject(ToastService);
  private readonly destroyRef = inject(DestroyRef);

  readonly agents = signal<AiAgent[]>([]);
  readonly isLoading = signal(true);
  readonly hasError = signal(false);
  readonly meta = signal({ current_page: 1, last_page: 1, per_page: 10, total: 0 });

  readonly showDeleteModal = signal(false);
  readonly agentToDelete = signal<AiAgent | null>(null);
  readonly isDeleting = signal(false);

  readonly isEmpty = () => !this.isLoading() && !this.hasError() && this.agents().length === 0;

  private readonly agentTypeLabels: Record<string, string> = {
    sales: 'Vendas',
    support: 'Suporte',
    qualifier: 'Qualificador',
    general: 'Geral',
    sales_qualifier: 'Qualificador de Vendas',
    cs_retention: 'Retenção de Clientes',
    support_l1: 'Suporte Nível 1',
    support_l2: 'Suporte Nível 2',
  };

  typeLabel(type: string | undefined): string {
    if (!type) return '—';
    return this.agentTypeLabels[type] ?? type;
  }

  private currentPage = 1;
  private currentSearch = '';

  constructor() {
    this.fetchAgents(1);
  }

  onSearch(term: string): void {
    this.currentSearch = term.trim();
    this.fetchAgents(1);
  }

  loadPage(page: number): void {
    this.fetchAgents(page);
  }

  retry(): void {
    this.fetchAgents(this.currentPage);
  }

  openCreate(): void {
    void this.router.navigate(['/ai/agents/new']);
  }

  openEdit(agent: AiAgent): void {
    void this.router.navigate(['/ai/agents', agent.id, 'edit']);
  }

  /**
   * Open agent workspace directly on Files tab.
   */
  openFiles(agent: AiAgent): void {
    void this.router.navigate(['/ai/agents', agent.id, 'edit'], {
      queryParams: { tab: 'files' },
    });
  }

  /**
   * Open simulator with selected agent.
   */
  openSimulator(agent: AiAgent): void {
    void this.router.navigate(['/ai/simulator'], { queryParams: { agent_id: agent.id } });
  }

  openDelete(agent: AiAgent): void {
    this.agentToDelete.set(agent);
    this.showDeleteModal.set(true);
  }

  handleDeleteConfirmed(): void {
    const agent = this.agentToDelete();
    if (!agent || this.isDeleting()) return;

    this.isDeleting.set(true);
    this.agentService
      .delete(agent.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.isDeleting.set(false);
          this.showDeleteModal.set(false);
          this.toast.success('Agente excluído.');
          this.fetchAgents(this.currentPage);
        },
        error: () => {
          this.isDeleting.set(false);
          this.showDeleteModal.set(false);
          this.toast.error('Erro ao excluir agente.');
        },
      });
  }

  private fetchAgents(page: number): void {
    this.currentPage = page;
    this.isLoading.set(true);
    this.hasError.set(false);

    this.agentService
      .list({ page: 1, per_page: 1000 })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          const search = this.currentSearch.toLowerCase();
          const perPage = this.meta().per_page;

          let data = [...(response.data ?? [])];
          if (search) {
            data = data.filter((item) => item.name.toLowerCase().includes(search));
          }

          const total = data.length;
          const lastPage = Math.max(1, Math.ceil(total / perPage));
          const start = (page - 1) * perPage;
          const paged = data.slice(start, start + perPage);

          this.agents.set(paged);
          this.meta.set({ total, per_page: perPage, current_page: page, last_page: lastPage });
          this.isLoading.set(false);
        },
        error: () => {
          this.hasError.set(true);
          this.isLoading.set(false);
        },
      });
  }
}
