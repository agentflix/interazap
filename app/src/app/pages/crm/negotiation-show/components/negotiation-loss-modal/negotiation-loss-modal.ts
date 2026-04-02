import { ChangeDetectionStrategy, Component, computed, input, output, signal } from '@angular/core';
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { ButtonComponent, LoadingButtonComponent } from '@shared/components/buttons';
import { SelectInputComponent, TextareaInputComponent } from '@shared/components/inputs';
import { ModalComponent } from '@shared/components/modal/modal';
import { type ReasonLoss } from 'src/app/core/services/reason-loss.service';

/**
 * Modal for choosing negotiation loss reason and optional comment.
 */
@Component({
  selector: 'app-negotiation-loss-modal',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    ModalComponent,
    ButtonComponent,
    LoadingButtonComponent,
    SelectInputComponent,
    TextareaInputComponent,
  ],
  templateUrl: './negotiation-loss-modal.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class NegotiationLossModalComponent {
  readonly isOpen = input(false);
  readonly reasonLosses = input<ReasonLoss[]>([]);
  readonly isUpdatingStatus = input(false);

  readonly confirmed = output<{ reasonId: string | number; comment?: string }>();
  readonly closed = output();

  readonly lossReasonId = signal<string | number | null>(null);
  readonly lossComment = signal('');
  readonly lossModalError = signal<string | null>(null);
  readonly reasonControl = new FormControl('', { nonNullable: true });
  readonly commentControl = new FormControl('', { nonNullable: true });

  readonly reasonOptions = computed(() =>
    this.reasonLosses().map((reason) => ({ label: reason.name, value: String(reason.id) })),
  );

  readonly selectedReason = computed(() => {
    const reasonId = this.lossReasonId();
    return this.reasonLosses().find((reason) => String(reason.id) === String(reasonId));
  });

  readonly requiresLossComment = computed(() => Boolean(this.selectedReason()?.requires_comment));

  constructor() {
    this.reasonControl.valueChanges.subscribe((value) => {
      this.lossReasonId.set(value || null);
    });

    this.commentControl.valueChanges.subscribe((value) => {
      this.lossComment.set(value || '');
    });
  }

  close(): void {
    this.closed.emit();
    this.reset();
  }

  confirm(): void {
    const reasonId = this.lossReasonId();
    const comment = this.lossComment().trim();

    if (reasonId === null || reasonId === '') {
      this.lossModalError.set('Selecione um motivo de perda.');
      return;
    }

    if (this.requiresLossComment() && !comment) {
      this.lossModalError.set('Descreva o motivo da perda.');
      return;
    }

    this.lossModalError.set(null);
    this.confirmed.emit({ reasonId, comment: comment || undefined });
  }

  private reset(): void {
    this.lossReasonId.set(null);
    this.lossComment.set('');
    this.lossModalError.set(null);
    this.reasonControl.setValue('', { emitEvent: false });
    this.commentControl.setValue('', { emitEvent: false });
  }
}
