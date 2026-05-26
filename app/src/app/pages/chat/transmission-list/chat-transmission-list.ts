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
import { type ChatTransmissionList, ChatTransmissionListService } from '@core/services/chat-transmission-list.service';
import { ToastService } from '@core/services/toast.service';

/**
 * Página de listagem de listas de transmissão em massa.
 *
 * @remarks
 * Exibe listagem paginada de listas de transmissão com busca, status e ações.
 * Permite criar, editar e excluir listas. Navega para o formulário em `/chat/transmission-list/new`.
 */
@Component({
  selector: 'app-chat-transmission-list',
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
  templateUrl: './chat-transmission-list.html',
})
export class ChatTransmissionListComponent implements OnInit {
  private readonly service = inject(ChatTransmissionListService);
  private readonly router = inject(Router);
  private readonly toast = inject(ToastService);
  private readonly destroyRef = inject(DestroyRef);

  readonly transmissionLists = signal<ChatTransmissionList[]>([]);
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
    () => !this.isLoading() && !this.hasError() && this.transmissionLists().length === 0,
  );

  ngOnInit(): void {
    this.loadTransmissionLists();
  }

  onSearch(term: string): void {
    this.searchTerm.set(term);
    this.page.set(1);
    this.loadTransmissionLists();
  }

  loadPage(nextPage: number): void {
    this.page.set(nextPage);
    this.loadTransmissionLists();
  }

  openCreate(): void {
    void this.router.navigate(['/chat/transmission-list/new']);
  }

  openEdit(transmissionList: ChatTransmissionList): void {
    void this.router.navigate(['/chat/transmission-list', transmissionList.id]);
  }

  handleCustomAction(event: { action: string; item: ChatTransmissionList }): void {
    if (event.action === 'open') {
      this.openEdit(event.item);
    }
  }

  remove(transmissionList: ChatTransmissionList): void {
    const confirmed = window.confirm(`Deseja excluir a lista de transmissão "${transmissionList.name}"?`);

    if (!confirmed) {
      return;
    }

    this.service
      .delete(transmissionList.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.toast.success('Lista de transmissão excluída com sucesso.');
          this.loadTransmissionLists();
        },
        error: () => {
          this.toast.error('Erro ao excluir lista de transmissão.');
        },
      });
  }

  retry(): void {
    this.loadTransmissionLists();
  }

  statusBadge(status: ChatTransmissionList['status']): 'online' | 'offline' | 'warning' | 'error' | 'idle' {
    const statusMap: Record<
      ChatTransmissionList['status'],
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

  statusLabel(status: ChatTransmissionList['status']): string {
    const labels: Record<ChatTransmissionList['status'], string> = {
      draft: 'Rascunho',
      scheduled: 'Agendada',
      running: 'Em execução',
      completed: 'Concluída',
      failed: 'Falhou',
      cancelled: 'Cancelada',
    };

    return labels[status];
  }

  private loadTransmissionLists(): void {
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
          this.transmissionLists.set(response.data.data);
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

export default ChatTransmissionListComponent;
