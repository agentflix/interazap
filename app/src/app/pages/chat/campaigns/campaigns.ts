import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  inject,
  signal,
  type OnInit,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { DatePipe } from '@angular/common';
import { Router } from '@angular/router';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfAlertComponent,
  AfButtonComponent,
  AfCardComponent,
  AfCrudPageComponent,
  AfStatusBadgeComponent,
} from '@shared/components';
import { type ChatCampaign, ChatCampaignService } from '@core/services/chat-campaign.service';
import { ToastService } from '@core/services/toast.service';

/**
 * Campaigns list page.
 *
 * @remarks
 * Preserves legacy behavior while using app-new UI Kit visual layer.
 * Exibe listagem paginada de campanhas com busca, status e acoes.
 *
 * @example
 * ```html
 * <app-campaigns />
 * ```
 */
@Component({
  selector: 'app-campaigns',
  standalone: true,
  imports: [
    DatePipe,
    LucideAngularModule,
    AfCrudPageComponent,
    AfAlertComponent,
    AfButtonComponent,
    AfCardComponent,
    AfStatusBadgeComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './campaigns.html',
})
export class CampaignsComponent implements OnInit {
  private readonly service = inject(ChatCampaignService);
  private readonly router = inject(Router);
  private readonly toast = inject(ToastService);
  private readonly destroyRef = inject(DestroyRef);

  readonly campaigns = signal<ChatCampaign[]>([]);
  readonly isLoading = signal(true);
  readonly hasError = signal(false);
  readonly searchTerm = signal('');
  readonly page = signal(1);
  readonly meta = signal({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
  });

  readonly isEmpty = computed(
    () => !this.isLoading() && !this.hasError() && this.campaigns().length === 0,
  );

  ngOnInit(): void {
    this.loadCampaigns();
  }

  onSearch(term: string): void {
    this.searchTerm.set(term);
    this.page.set(1);
    this.loadCampaigns();
  }

  loadPage(nextPage: number): void {
    this.page.set(nextPage);
    this.loadCampaigns();
  }

  openCreate(): void {
    void this.router.navigate(['/chat/campaigns/new']);
  }

  openEdit(campaign: ChatCampaign): void {
    void this.router.navigate(['/chat/campaigns', campaign.id]);
  }

  handleCustomAction(event: { action: string; item: ChatCampaign }): void {
    if (event.action === 'open') {
      this.openEdit(event.item);
    }
  }

  remove(campaign: ChatCampaign): void {
    const confirmed = window.confirm(`Deseja excluir a campanha "${campaign.name}"?`);

    if (!confirmed) {
      return;
    }

    this.service
      .delete(campaign.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.toast.success('Campanha excluída com sucesso.');
          this.loadCampaigns();
        },
        error: () => {
          this.toast.error('Erro ao excluir campanha.');
        },
      });
  }

  retry(): void {
    this.loadCampaigns();
  }

  statusBadge(status: ChatCampaign['status']): 'online' | 'offline' | 'warning' | 'error' | 'idle' {
    const statusMap: Record<
      ChatCampaign['status'],
      'online' | 'offline' | 'warning' | 'error' | 'idle'
    > = {
      draft: 'warning',
      scheduled: 'idle',
      running: 'idle',
      completed: 'online',
      failed: 'error',
      cancelled: 'offline',
    };

    return statusMap[status];
  }

  statusLabel(status: ChatCampaign['status']): string {
    const labels: Record<ChatCampaign['status'], string> = {
      draft: 'Rascunho',
      scheduled: 'Agendada',
      running: 'Em execução',
      completed: 'Concluída',
      failed: 'Falhou',
      cancelled: 'Cancelada',
    };

    return labels[status];
  }

  private loadCampaigns(): void {
    this.isLoading.set(true);
    this.hasError.set(false);

    this.service
      .list({
        page: this.page(),
        per_page: this.meta().per_page,
        search: this.searchTerm() || undefined,
      })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.campaigns.set(response.data.data);
          this.meta.set({
            current_page: response.data.current_page ?? 1,
            last_page: response.data.last_page ?? 1,
            per_page: response.data.per_page ?? 10,
            total: response.data.total ?? 0,
          });
          this.isLoading.set(false);
        },
        error: () => {
          this.isLoading.set(false);
          this.hasError.set(true);
        },
      });
  }
}

export default CampaignsComponent;
