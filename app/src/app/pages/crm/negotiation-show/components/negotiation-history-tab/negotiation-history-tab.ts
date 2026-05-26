import {
  type OnInit,
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  inject,
  input,
  output,
  signal,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormControl, ReactiveFormsModule, Validators } from '@angular/forms';
import { LucideAngularModule } from 'lucide-angular';
import { toast } from 'ngx-sonner';
import {
  type NegotiationAnnotation,
  NegotiationAnnotationService,
} from 'src/app/core/services/negotiation-annotation.service';
import { ButtonComponent, LoadingButtonComponent } from '@shared/components/buttons';
import { ModalComponent } from '@shared/components/modal/modal';
import { TextareaInputComponent } from '@shared/components/inputs';

/**
 * Conteúdo da aba de histórico — gerencia as anotações e o histórico de atividades da negociação.
 */
@Component({
  selector: 'app-negotiation-history-tab',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    LucideAngularModule,
    ButtonComponent,
    LoadingButtonComponent,
    ModalComponent,
    TextareaInputComponent,
  ],
  templateUrl: './negotiation-history-tab.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class NegotiationHistoryTabComponent implements OnInit {
  private readonly annotationService = inject(NegotiationAnnotationService);
  private readonly destroyRef = inject(DestroyRef);

  readonly negotiationId = input.required<string | number>();
  readonly currentUserId = input<string | number | null>(null);
  readonly failed = output<string>();

  readonly annotations = signal<NegotiationAnnotation[]>([]);
  readonly isAnnotationsLoading = signal(false);
  readonly isAnnotationModalOpen = signal(false);
  readonly isAnnotationSaving = signal(false);
  readonly annotationModalError = signal<string | null>(null);
  readonly editingAnnotation = signal<NegotiationAnnotation | null>(null);
  readonly annotationControl = new FormControl('', {
    nonNullable: true,
    validators: [Validators.required, Validators.maxLength(5000)],
  });

  readonly annotationCount = computed(() => this.annotations().length);

  ngOnInit(): void {
    this.loadAnnotations();
  }

  loadAnnotations(): void {
    this.isAnnotationsLoading.set(true);
    this.annotationService
      .list(this.negotiationId())
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.annotations.set(response.data.annotations ?? []);
          this.isAnnotationsLoading.set(false);
        },
        error: () => {
          this.annotations.set([]);
          this.isAnnotationsLoading.set(false);
          this.failed.emit('Não foi possível carregar o histórico da negociação.');
        },
      });
  }

  openAnnotationModal(): void {
    this.editingAnnotation.set(null);
    this.annotationModalError.set(null);
    this.annotationControl.setValue('');
    this.isAnnotationModalOpen.set(true);
  }

  closeAnnotationModal(): void {
    this.isAnnotationModalOpen.set(false);
    this.editingAnnotation.set(null);
    this.isAnnotationSaving.set(false);
    this.annotationControl.reset('');
  }

  saveAnnotation(): void {
    if (this.annotationControl.invalid) {
      this.annotationControl.markAsTouched();
      return;
    }

    const content = this.annotationControl.value.trim();
    if (!content) {
      this.annotationModalError.set('Informe um conteúdo para a anotação.');
      return;
    }

    const request = this.annotationService.create(this.negotiationId(), {
      content,
      type: 'manual',
    });

    this.isAnnotationSaving.set(true);

    request.pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: () => {
        this.isAnnotationSaving.set(false);
        toast.success('Anotação adicionada.');
        this.closeAnnotationModal();
        this.loadAnnotations();
      },
      error: () => {
        this.isAnnotationSaving.set(false);
        this.annotationModalError.set('Não foi possível salvar a anotação.');
      },
    });
  }

  getAnnotationTypeMeta(type?: string | null): { label: string; icon: string; tone: string } {
    switch (type) {
      case 'system':
        return {
          label: 'Sistema',
          icon: 'settings',
          tone: 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400',
        };
      case 'status':
        return { label: 'Status', icon: 'circle-alert', tone: 'bg-warning/10 text-warning' };
      case 'call':
        return { label: 'Ligação', icon: 'phone', tone: 'bg-info/10 text-info' };
      case 'email':
        return { label: 'E-mail', icon: 'mail', tone: 'bg-primary/10 text-primary' };
      case 'meeting':
        return { label: 'Reunião', icon: 'users', tone: 'bg-success/10 text-success' };
      default:
        return {
          label: 'Nota',
          icon: 'message-square',
          tone: 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400',
        };
    }
  }

  formatDateTime(value?: string | null): string {
    if (!value) return '-';
    const date = new Date(value);
    return Number.isNaN(date.getTime())
      ? '-'
      : date.toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
  }
}
