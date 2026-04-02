import {
  ChangeDetectionStrategy,
  Component,
  computed,
  effect,
  inject,
  input,
  output,
  signal,
  type OnDestroy,
} from '@angular/core';
import { type Subscription } from 'rxjs';
import { NgIcon, provideIcons } from '@ng-icons/core';
import { lucideHandshake, lucidePlus } from '@ng-icons/lucide';
import { NegotiationService } from 'src/app/core/services/negotiation.service';
import { DealCardComponent } from './deal-card/deal-card.component';
import { DealEditModalComponent } from './deal-edit-modal/deal-edit-modal.component';
import { DealLossModalComponent } from './deal-loss-modal/deal-loss-modal.component';
import { DealWinModalComponent } from './deal-win-modal/deal-win-modal.component';
import { IconButtonComponent } from 'src/app/shared/components/buttons';
import { type CRMNegotiation } from './crm-negotiation.model';
import { AfScrollAreaComponent } from 'src/app/shared/components/scroll-area/scroll-area';

/**
 * Componente container da secao CRM do sidebar.
 *
 * @remarks
 * Lista negociacoes com ordenacao: andamento -> ganhas -> perdidas.
 * Gerencia modais de criar, editar, marcar como ganha ou perdida.
 *
 * @example
 * ```html
 * <app-crm-section
 *   [contactId]="contactId"
 *   (dealCreated)="onDealCreated($event)" />
 * ```
 */
@Component({
  selector: 'app-crm-section',
  templateUrl: './crm-section.html',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    NgIcon,
    IconButtonComponent,
    DealCardComponent,
    DealEditModalComponent,
    DealLossModalComponent,
    DealWinModalComponent,
    AfScrollAreaComponent,
  ],
  host: { class: 'block' },
  viewProviders: [provideIcons({ lucideHandshake, lucidePlus })],
})
export class CRMSectionComponent implements OnDestroy {
  private readonly negotiationService = inject(NegotiationService);
  private loadNegotiationsSub: Subscription | null = null;

  readonly contactId = input.required<string>();
  readonly dealCreated = output<CRMNegotiation>();

  readonly negotiations = signal<CRMNegotiation[]>([]);
  readonly isLoading = signal(true);
  readonly showEmpty = computed(() => !this.isLoading() && this.negotiations().length === 0);
  readonly showEditModal = signal(false);
  readonly showLossModal = signal(false);
  readonly showWinModal = signal(false);
  readonly selectedDeal = signal<CRMNegotiation | null>(null);

  /**
   * Negociações ordenadas: abertas → ganhas → perdidas.
   */
  readonly sortedNegotiations = computed(() => {
    const deals = this.negotiations();
    const statusOrder: Record<string, number> = { open: 1, won: 2, lost: 3 };
    return [...deals].sort((a, b) => statusOrder[a.status] - statusOrder[b.status]);
  });

  constructor() {
    /** Recarrega negociações sempre que o contactId mudar. */
    effect(() => {
      const cid = this.contactId();
      if (!cid) {
        this.negotiations.set([]);
        this.isLoading.set(false);
        return;
      }
      this.loadNegotiations();
    });
  }

  /**
   * Carregar negocia\u00e7\u00f5es do contato.
   */
  loadNegotiations(): void {
    this.loadNegotiationsSub?.unsubscribe();
    this.isLoading.set(true);
    this.loadNegotiationsSub = this.negotiationService
      .list({ contact_id: this.contactId(), per_page: 50 })
      .subscribe({
        next: (response) => {
          this.negotiations.set(response.data ?? []);
          this.isLoading.set(false);
        },
        error: () => {
          this.isLoading.set(false);
        },
      });
  }

  ngOnDestroy(): void {
    this.loadNegotiationsSub?.unsubscribe();
  }

  /**
   * Abrir modal para criar nova negocia\u00e7\u00e3o.
   */
  openNewDealModal(): void {
    this.selectedDeal.set(null);
    this.showEditModal.set(true);
  }

  /**
   * Abrir modal de edi\u00e7\u00e3o para uma negocia\u00e7\u00e3o existente.
   */
  openEditModal(deal: CRMNegotiation): void {
    this.selectedDeal.set(deal);
    this.showEditModal.set(true);
  }

  /**
   * Abrir modal para marcar como perdida.
   */
  openLossModal(deal: CRMNegotiation): void {
    this.selectedDeal.set(deal);
    this.showLossModal.set(true);
  }

  /**
   * Abrir modal para marcar como ganha.
   */
  openWinModal(deal: CRMNegotiation): void {
    this.selectedDeal.set(deal);
    this.showWinModal.set(true);
  }

  /**
   * Handler ao salvar negocia\u00e7\u00e3o (criar ou editar).
   */
  onDealSaved(deal: CRMNegotiation): void {
    this.showEditModal.set(false);
    this.loadNegotiations();
    if (!this.selectedDeal()) {
      this.dealCreated.emit(deal);
    }
  }

  /**
   * Handler ao marcar como perdida.
   */
  onDealLost(): void {
    this.showLossModal.set(false);
    this.loadNegotiations();
  }

  /**
   * Handler ao marcar como ganha.
   */
  onDealWon(): void {
    this.showWinModal.set(false);
    this.loadNegotiations();
  }

  /**
   * Handler ao mudar etapa da negocia\u00e7\u00e3o.
   */
  onStageChanged(dealId: string | number, direction: 'previous' | 'next'): void {
    const deal = this.negotiations().find((n) => n.id === dealId);
    if (!deal || !deal.step || !deal.funnel?.steps) return;

    const orderedSteps = [...deal.funnel.steps].sort((a, b) => a.order - b.order);
    const currentIndex = orderedSteps.findIndex((step) => step.id === deal.step?.id);
    if (currentIndex < 0) return;

    const targetIndex = direction === 'previous' ? currentIndex - 1 : currentIndex + 1;
    const targetStep = orderedSteps[targetIndex];
    if (!targetStep) return;

    this.negotiationService
      .move(dealId, targetStep.id, deal.position ?? targetIndex + 1)
      .subscribe({
        next: (response) => {
          const updated = response.data.negotiation as CRMNegotiation;
          this.negotiations.update((deals) =>
            deals.map((d) => (d.id === dealId ? { ...d, ...updated } : d)),
          );
        },
      });
  }
}

export type { CRMNegotiation } from './crm-negotiation.model';
