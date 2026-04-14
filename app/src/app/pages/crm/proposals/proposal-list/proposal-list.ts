import { CommonModule, DatePipe } from '@angular/common';
import {
  type OnInit,
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  inject,
  input,
  signal,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { LucideAngularModule } from 'lucide-angular';
import { ConfirmModalComponent } from 'src/app/shared/components/confirm-modal/confirm-modal';
import { ModalComponent } from 'src/app/shared/components/modal/modal';
import { ButtonComponent } from 'src/app/shared/components/button/button';
import { IconButtonComponent } from 'src/app/shared/components/icon-button/icon-button';
import {
  NegotiationProductService,
  type NegotiationProductItem,
} from 'src/app/core/services/negotiation-product.service';
import {
  type Proposal,
  type ProposalItem,
  CRMProposalService,
} from '../../services/crm-proposal.service';
import { ProposalFormComponent } from '../proposal-form/proposal-form';

@Component({
  selector: 'app-proposal-list',
  standalone: true,
  imports: [
    CommonModule,
    LucideAngularModule,
    DatePipe,
    ModalComponent,
    ConfirmModalComponent,
    ProposalFormComponent,
    ButtonComponent,
    IconButtonComponent,
/**
 * Proposal list page component for the Crm module.
 * @selector app-proposal-list
 */
  ],
  templateUrl: './proposal-list.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProposalListComponent implements OnInit {
  readonly negotiationId = input.required<string | number>();

  private readonly destroyRef = inject(DestroyRef);
  private readonly proposalService = inject(CRMProposalService);
  private readonly negotiationProductService = inject(NegotiationProductService);

  readonly proposals = signal<Proposal[]>([]);
  readonly isLoading = signal(false);
  readonly isPreparingForm = signal(false);
  readonly errorMessage = signal<string | null>(null);
  readonly prefillItems = signal<ProposalItem[]>([]);

  readonly isFormOpen = signal(false);
  readonly isDeleteOpen = signal(false);
  readonly selectedProposal = signal<Proposal | null>(null);
  readonly isDeleting = signal(false);

  constructor() {
    // Load is called from ngOnInit to avoid NG0950 with required input signals
  }

  ngOnInit(): void {
    this.load();
  }

  load(): void {
    if (!this.negotiationId()) return;

    this.isLoading.set(true);
    this.errorMessage.set(null);

    this.proposalService
      .listByNegotiation(this.negotiationId())
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.proposals.set(response.data);
          this.isLoading.set(false);
        },
        error: () => {
          this.errorMessage.set('Não foi possível carregar as propostas.');
          this.isLoading.set(false);
        },
      });
  }

  openCreate(): void {
    this.selectedProposal.set(null);
    this.prefillItems.set([]);
    this.isPreparingForm.set(true);
    this.errorMessage.set(null);

    this.negotiationProductService
      .list(this.negotiationId())
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.prefillItems.set(this.mapProductsToProposalItems(response.data.products));
          this.isPreparingForm.set(false);
          this.isFormOpen.set(true);
        },
        error: () => {
          this.prefillItems.set([]);
          this.isPreparingForm.set(false);
          this.errorMessage.set(
            'Não foi possível carregar os produtos para pré-preencher a proposta.',
          );
          this.isFormOpen.set(true);
        },
      });
  }

  openEdit(proposal: Proposal): void {
    this.prefillItems.set([]);
    this.selectedProposal.set(proposal);
    this.isFormOpen.set(true);
  }

  closeForm(): void {
    this.isFormOpen.set(false);
  }

  onSaved(): void {
    this.isFormOpen.set(false);
    this.load();
  }

  confirmDelete(proposal: Proposal): void {
    this.selectedProposal.set(proposal);
    this.isDeleteOpen.set(true);
  }

  cancelDelete(): void {
    this.isDeleteOpen.set(false);
  }

  deleteConfirmed(): void {
    const proposal = this.selectedProposal();
    if (!proposal) return;

    this.isDeleting.set(true);
    this.proposalService
      .delete(proposal.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.isDeleting.set(false);
          this.isDeleteOpen.set(false);
          this.load();
        },
        error: () => {
          this.isDeleting.set(false);
          this.errorMessage.set('Não foi possível excluir a proposta.');
        },
      });
  }

  sendProposal(proposal: Proposal): void {
    this.proposalService
      .send(proposal.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => this.load(),
        error: () => this.errorMessage.set('Não foi possível enviar a proposta.'),
      });
  }

  duplicateProposal(proposal: Proposal): void {
    this.proposalService
      .duplicate(proposal.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => this.load(),
        error: () => this.errorMessage.set('Não foi possível duplicar a proposta.'),
      });
  }

  openPublic(proposal: Proposal): void {
    if (!proposal.public_token) return;
    window.open(`/proposal/${proposal.public_token}`, '_blank');
  }

  trackById(index: number, item: Proposal): string | number {
    return item.id ?? index;
  }

  statusClass(status: string): string {
    const map: Record<string, string> = {
      draft: 'bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300',
      sent: 'bg-info/10 text-info',
      accepted: 'bg-success/10 text-success',
      rejected: 'bg-danger/10 text-danger',
    };

    return (
      map[status] ?? 'bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300'
    );
  }

  private mapProductsToProposalItems(products: NegotiationProductItem[]): ProposalItem[] {
    return products.map((product, index) => ({
      name: product.product?.name ?? 'Item',
      quantity: Number(product.quantity || 1),
      unit_price: Number(product.price || 0),
      discount: Number(product.discount || 0),
      crm_product_id: product.product_id,
      position: index + 1,
    }));
  }
}
