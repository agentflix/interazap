import { CommonModule } from '@angular/common';
import { ChangeDetectionStrategy, Component, input, output } from '@angular/core';
import { NgIcon, provideIcons } from '@ng-icons/core';
import { lucidePlus } from '@ng-icons/lucide';
import { type Contact } from 'src/app/core/models/contact.model';
import { type CalledContactSummary } from 'src/app/core/services/called.service';
import { type Funnel, type FunnelStep } from 'src/app/core/services/funnel.service';
import { type Negotiation } from 'src/app/core/services/negotiation.service';
import { AfScrollAreaComponent } from 'src/app/shared/components/scroll-area/scroll-area';

type ChatNegotiationContact =
  | Contact
  | (CalledContactSummary & { crm_company_id?: string | number | null });

/** Dados do formulário de criação de negociação no painel lateral. */
interface NegotiationCreateForm {
  title: string;
  funnelId: string;
  stepId: string;
  expectedClose: string;
  notes: string;
  companyId: string;
}

/** Evento de alteração de campos do formulário de criação. */
interface CreateFieldChangeEvent {
  field: keyof NegotiationCreateForm;
  value: string;
}

@Component({
  selector: 'app-negotiation-timeline',
  standalone: true,
  imports: [CommonModule, NgIcon, AfScrollAreaComponent],
  templateUrl: './negotiation-timeline.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  viewProviders: [
    provideIcons({
      lucidePlus,
    }),
  ],
})
export class NegotiationTimelineComponent {
  readonly currentContact = input<ChatNegotiationContact | null>(null);
  readonly isLoadingList = input<boolean>(false);
  readonly listError = input<string | null>(null);
  readonly hasNegotiations = input<boolean>(false);
  readonly negotiations = input<Negotiation[]>([]);
  readonly selectedNegotiationId = input<string | number | null>(null);

  readonly createError = input<string | null>(null);
  readonly createForm = input.required<NegotiationCreateForm>();
  readonly isCreating = input<boolean>(false);
  readonly canCreateNegotiation = input<boolean>(false);
  readonly funnels = input<Funnel[]>([]);
  readonly createSteps = input<FunnelStep[]>([]);

  readonly selectNegotiation = output<string | number>();
  readonly createFieldChange = output<CreateFieldChangeEvent>();
  readonly createNegotiation = output<void>();

  isSelectedNegotiation(id: string | number): boolean {
    return String(this.selectedNegotiationId()) === String(id);
  }

  formatDate(value?: string | null): string {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '-';
    return date.toLocaleDateString('pt-BR');
  }

  statusTone(status?: string | null): string {
    switch (status) {
      case 'won':
        return 'bg-success/10 text-success';
      case 'lost':
        return 'bg-danger/10 text-danger';
      default:
        return 'bg-primary/10 text-primary';
    }
  }

  emitSelectNegotiation(id: string | number): void {
    this.selectNegotiation.emit(id);
  }

  onCreateTextInput(field: keyof NegotiationCreateForm, event: Event): void {
    this.createFieldChange.emit({
      field,
      value: this.readInputValue(event),
    });
  }

  onCreateTextAreaInput(field: keyof NegotiationCreateForm, event: Event): void {
    this.createFieldChange.emit({
      field,
      value: this.readTextAreaValue(event),
    });
  }

  onCreateSelectInput(field: keyof NegotiationCreateForm, event: Event): void {
    this.createFieldChange.emit({
      field,
      value: this.readSelectValue(event),
    });
  }

  emitCreateNegotiation(): void {
    this.createNegotiation.emit();
  }

  private readInputValue(event: Event): string {
    return (event.target as HTMLInputElement | null)?.value ?? '';
  }

  private readSelectValue(event: Event): string {
    return (event.target as HTMLSelectElement | null)?.value ?? '';
  }

  private readTextAreaValue(event: Event): string {
    return (event.target as HTMLTextAreaElement | null)?.value ?? '';
  }
}
