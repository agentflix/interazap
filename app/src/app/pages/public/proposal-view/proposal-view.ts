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
 * Página pública de visualização de proposta comercial.
 * Acessível via link com token seguro, sem autenticação, permitindo aceitar ou rejeitar a proposta.
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

  /**
   * Carrega os dados da proposta a partir do token público.
   * @param token Token público da proposta
   */
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

  /** Aceita a proposta exibida via API pública. */
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

  /** Rejeita a proposta exibida via API pública. */
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

  /**
   * Função de rastreamento por ID para laços @for.
   * @param index Índice do elemento
   * @param item Objeto com propriedade id opcional
   * @returns ID do item ou índice como fallback
   */
  trackById(index: number, item: { id?: string | number }): string | number {
    return item.id ?? index;
  }
}
