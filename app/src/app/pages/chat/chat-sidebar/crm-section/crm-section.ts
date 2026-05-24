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
import { finalize, type Subscription } from 'rxjs';
import { NgIcon, provideIcons } from '@ng-icons/core';
import { lucideHandshake, lucidePlus } from '@ng-icons/lucide';
import { NegotiationService } from 'src/app/core/services/negotiation.service';
import { FunnelService, type FunnelStep } from 'src/app/core/services/funnel.service';
import { DealCardComponent } from './deal-card/deal-card.component';
import { DealEditModalComponent } from './deal-edit-modal/deal-edit-modal.component';
import { DealLossModalComponent } from './deal-loss-modal/deal-loss-modal.component';
import { DealWinModalComponent } from './deal-win-modal/deal-win-modal.component';
import { IconButtonComponent } from 'src/app/shared/components/buttons';
import { type CRMNegotiation } from './crm-negotiation.model';
import { AfScrollAreaComponent } from 'src/app/shared/components/scroll-area/scroll-area';
import { AfEmptyStateComponent } from 'src/app/shared/components/empty-state/empty-state';

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
    AfEmptyStateComponent,
  ],
  host: { class: 'block h-full' },
  viewProviders: [provideIcons({ lucideHandshake, lucidePlus })],
})
export class CRMSectionComponent implements OnDestroy {
  private readonly negotiationService = inject(NegotiationService);
  private readonly funnelService = inject(FunnelService);
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
  readonly stageUpdatingByDeal = signal<Record<string, boolean>>({});
  readonly funnelStepsByFunnelId = signal<Record<string, FunnelStep[]>>({});

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
          const deals = response.data ?? [];
          this.negotiations.set(deals);
          this.hydrateFunnelSteps(deals);
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
    const orderedSteps = this.getOrderedStepsForDeal(deal);
    if (!deal || !deal.step || orderedSteps.length === 0) return;

    const currentIndex = orderedSteps.findIndex((step) => step.id === deal.step?.id);
    if (currentIndex < 0) return;

    const targetIndex = direction === 'previous' ? currentIndex - 1 : currentIndex + 1;
    const targetStep = orderedSteps[targetIndex];
    if (!targetStep) return;
    const dealKey = String(dealId);
    this.stageUpdatingByDeal.update((state) => ({ ...state, [dealKey]: true }));

    this.negotiationService
      .move(dealId, targetStep.id, deal.position ?? targetIndex + 1)
      .pipe(
        finalize(() => {
          this.stageUpdatingByDeal.update((state) => ({ ...state, [dealKey]: false }));
        }),
      )
      .subscribe({
        next: (response) => {
          const raw = response.data as Record<string, unknown>;
          const updated = (raw['negotiation'] ?? raw) as CRMNegotiation;
          this.negotiations.update((deals) =>
            deals.map((d) => {
              if (d.id !== dealId) {
                return d;
              }

              return {
                ...d,
                ...updated,
                funnel: d.funnel ?? updated.funnel,
                step: updated.step ?? targetStep,
                step_id: updated.step_id ?? targetStep.id,
                position: updated.position ?? targetIndex + 1,
              };
            }),
          );
        },
        error: () => {
          // no-op: estado de loading por negociação já é liberado no finalize
        },
      });
  }

  isStageUpdating(dealId: string | number): boolean {
    return this.stageUpdatingByDeal()[String(dealId)] === true;
  }

  private hydrateFunnelSteps(deals: CRMNegotiation[]): void {
    const funnelIds = Array.from(
      new Set(
        deals
          .map((deal) => deal.funnel?.id ?? deal.funnel_id)
          .filter((id): id is string | number => id !== undefined && id !== null),
      ),
    );

    funnelIds.forEach((funnelId) => {
      const key = String(funnelId);
      if (this.funnelStepsByFunnelId()[key]) {
        return;
      }

      this.funnelService.listSteps(funnelId).subscribe({
        next: (response) => {
          const steps = [...(response.data.steps ?? [])].sort((a, b) => a.order - b.order);
          this.funnelStepsByFunnelId.update((state) => ({ ...state, [key]: steps }));
          this.negotiations.update((currentDeals) =>
            currentDeals.map((deal) => {
              const dealFunnelId = deal.funnel?.id ?? deal.funnel_id;
              if (String(dealFunnelId ?? '') !== key) {
                return deal;
              }

              return {
                ...deal,
                funnel: {
                  ...(deal.funnel ?? { id: String(funnelId), name: '', is_active: true }),
                  steps,
                },
              };
            }),
          );
        },
      });
    });
  }

  private getOrderedStepsForDeal(deal: CRMNegotiation | undefined): FunnelStep[] {
    if (!deal) {
      return [];
    }

    const directSteps = deal.funnel?.steps;
    if (Array.isArray(directSteps) && directSteps.length > 0) {
      return [...directSteps].sort((a, b) => a.order - b.order);
    }

    const funnelId = deal.funnel?.id ?? deal.funnel_id;
    if (!funnelId) {
      return [];
    }

    const cachedSteps = this.funnelStepsByFunnelId()[String(funnelId)] ?? [];
    return [...cachedSteps].sort((a, b) => a.order - b.order);
  }
}

export type { CRMNegotiation } from './crm-negotiation.model';
