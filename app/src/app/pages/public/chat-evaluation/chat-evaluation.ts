import { HttpClient } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, DestroyRef, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute } from '@angular/router';
import {
  AfAlertComponent,
  AfEmptyStateComponent,
  AfLoadingButtonComponent,
  AfSkeletonComponent,
} from '@shared/components';

/** Resposta da API ao verificar status do token de avaliação. */
interface EvaluationShowResponse {
  data: {
    submitted: boolean;
    token?: string;
    message?: string;
  };
}

/** Resposta da API ao submeter avaliação. */
interface EvaluationSubmitResponse {
  data: {
    message: string;
  };
}

/**
 * Página pública de avaliação de atendimento via chat.
 *
 * Permite que clientes avaliem o atendimento recebido
 * usando um link com token seguro, sem autenticação.
 */
@Component({
  selector: 'app-chat-evaluation',
  standalone: true,
  imports: [
    FormsModule,
    AfAlertComponent,
    AfEmptyStateComponent,
    AfLoadingButtonComponent,
    AfSkeletonComponent,
  ],
  templateUrl: './chat-evaluation.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export default class ChatEvaluationComponent {
  private readonly route = inject(ActivatedRoute);
  private readonly http = inject(HttpClient);
  private readonly destroyRef = inject(DestroyRef);

  /** Índices das estrelas (1-5). */
  readonly starIndexes = [1, 2, 3, 4, 5];

  /** Estado de carregamento inicial. */
  readonly isLoading = signal(true);

  /** Token não encontrado ou inválido. */
  readonly notFound = signal(false);

  /** Avaliação já foi submetida anteriormente. */
  readonly alreadySubmitted = signal(false);

  /** Avaliação acabou de ser submetida com sucesso. */
  readonly submitted = signal(false);

  /** Rating selecionado (0 = nenhum). */
  readonly selectedRating = signal(0);

  /** Comentário digitado. */
  readonly comment = signal('');

  /** Erro de validação do rating. */
  readonly ratingError = signal<string | null>(null);

  /** Erro geral da API. */
  readonly errorMessage = signal<string | null>(null);

  /** Estado de submissão. */
  readonly isSubmitting = signal(false);

  /** Token da avaliação. */
  private token = '';

  constructor() {
    this.route.paramMap.pipe(takeUntilDestroyed(this.destroyRef)).subscribe((params) => {
      const token = params.get('token');
      if (token !== null && token !== '') {
        this.token = token;
        this.loadEvaluation(token);
      } else {
        this.isLoading.set(false);
        this.notFound.set(true);
      }
    });
  }

  /** Seleciona uma nota e limpa erro de validação. */
  selectRating(rating: number): void {
    this.selectedRating.set(rating);
    this.ratingError.set(null);
  }

  /** Envia a avaliação para a API. */
  submitEvaluation(): void {
    if (this.selectedRating() < 1 || this.selectedRating() > 5) {
      this.ratingError.set('Selecione uma nota de 1 a 5 estrelas.');
      return;
    }

    this.isSubmitting.set(true);
    this.errorMessage.set(null);

    const body = {
      rating: this.selectedRating(),
      comment: this.comment().trim() || null,
    };

    this.http
      .post<EvaluationSubmitResponse>(
        `/api/public/chat/evaluations/${encodeURIComponent(this.token)}`,
        body,
      )
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.isSubmitting.set(false);
          this.submitted.set(true);
        },
        error: () => {
          this.isSubmitting.set(false);
          this.errorMessage.set('Não foi possível enviar a avaliação. Tente novamente.');
        },
      });
  }

  /** Carrega o status do token de avaliação. */
  private loadEvaluation(token: string): void {
    this.isLoading.set(true);

    this.http
      .get<EvaluationShowResponse>(`/api/public/chat/evaluations/${encodeURIComponent(token)}`)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.isLoading.set(false);

          if (response.data.submitted) {
            this.alreadySubmitted.set(true);
          }
        },
        error: () => {
          this.isLoading.set(false);
          this.notFound.set(true);
        },
      });
  }
}
