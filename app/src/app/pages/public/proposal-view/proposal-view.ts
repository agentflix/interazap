import { DatePipe, CurrencyPipe, UpperCasePipe } from '@angular/common';
import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  inject,
  signal,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ActivatedRoute } from '@angular/router';
import {
  AfAlertComponent,
  AfButtonComponent,
  AfEmptyStateComponent,
  AfPdfPreviewComponent,
  AfSkeletonComponent,
} from '@shared/components';
import { CRMProposalService, type Proposal } from '@crm/services/crm-proposal.service';

/**
 * Proposal view page component for the Public module.
 * @selector app-proposal-view
 */
@Component({
  selector: 'app-proposal-view',
  standalone: true,
  imports: [
    DatePipe,
    CurrencyPipe,
    UpperCasePipe,
    AfAlertComponent,
    AfButtonComponent,
    AfEmptyStateComponent,
    AfPdfPreviewComponent,
    AfSkeletonComponent,
  ],
  templateUrl: './proposal-view.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export default class ProposalViewComponent {
  private readonly route = inject(ActivatedRoute);
  private readonly proposalService = inject(CRMProposalService);
  private readonly destroyRef = inject(DestroyRef);

  readonly proposal = signal<Proposal | null>(null);
  readonly isLoading = signal(false);
  readonly errorMessage = signal<string | null>(null);
  readonly isProcessing = signal(false);
  readonly successMessage = signal<string | null>(null);

  readonly proposalPdfUrl = computed(() => this.proposal()?.pdf_url ?? null);

  constructor() {
    this.route.paramMap.pipe(takeUntilDestroyed(this.destroyRef)).subscribe((params) => {
      const token = params.get('token');
      if (token) {
        this.load(token);
      }
    });
  }

  load(token: string): void {
    this.isLoading.set(true);
    this.errorMessage.set(null);

    this.proposalService
      .publicView(token)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.proposal.set(response.data.proposal);
          this.isLoading.set(false);
        },
        error: () => {
          this.isLoading.set(false);
          this.errorMessage.set('Proposta não encontrada.');
        },
      });
  }

  accept(): void {
    const token = this.proposal()?.public_token;
    if (!token) {
      return;
    }

    this.isProcessing.set(true);
    this.successMessage.set(null);
    this.proposalService
      .publicAccept(token)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.proposal.set(response.data.proposal);
          this.isProcessing.set(false);
          this.successMessage.set('Proposta aceita com sucesso.');
        },
        error: () => {
          this.isProcessing.set(false);
          this.errorMessage.set('Não foi possível aceitar a proposta.');
        },
      });
  }

  reject(): void {
    const token = this.proposal()?.public_token;
    if (!token) {
      return;
    }

    this.isProcessing.set(true);
    this.successMessage.set(null);
    this.proposalService
      .publicReject(token)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.proposal.set(response.data.proposal);
          this.isProcessing.set(false);
          this.successMessage.set('Proposta rejeitada.');
        },
        error: () => {
          this.isProcessing.set(false);
          this.errorMessage.set('Não foi possível rejeitar a proposta.');
        },
      });
  }

  trackById(index: number, item: { id?: string | number }): string | number {
    return item.id ?? index;
  }
}
