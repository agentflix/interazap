import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  effect,
  inject,
  input,
  output,
  signal,
} from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { LucideAngularModule } from 'lucide-angular';
import {
  type SelectOption,
  TextInputComponent,
  TextareaInputComponent,
  CheckboxInputComponent,
  SelectInputComponent,
} from '@shared/components/inputs';
import {
  type Event as CRMEvent,
  type EventStatus,
  type EventType,
  EventService,
} from 'src/app/core/services/event.service';

@Component({
  selector: 'app-agenda-form',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    LucideAngularModule,
    TextInputComponent,
    TextareaInputComponent,
    CheckboxInputComponent,
    SelectInputComponent,
/**
 * Agenda form component for the Crm module.
 * @selector app-agenda-form
 */
  ],
  templateUrl: './agenda-form.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AgendaFormComponent {
  private readonly fb = inject(NonNullableFormBuilder);
  private readonly eventService = inject(EventService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly lastLoadedId = signal<string | null>(null);

  readonly event = input<CRMEvent | null>(null);
  readonly initialData = input<Partial<CRMEvent> | null>(null);
  readonly saved = output<CRMEvent>();
  readonly cancelled = output<void>();

  readonly isSaving = signal(false);

  readonly statusSelectOptions: SelectOption[] = [
    { value: 'scheduled', label: 'Agendado' },
    { value: 'completed', label: 'Concluído' },
    { value: 'cancelled', label: 'Cancelado' },
  ];

  readonly typeSelectOptions: SelectOption[] = [
    { value: 'meeting', label: 'Reunião' },
    { value: 'call', label: 'Ligação' },
    { value: 'task', label: 'Tarefa' },
    { value: 'deadline', label: 'Prazo' },
    { value: 'reminder', label: 'Lembrete' },
    { value: 'other', label: 'Outro' },
  ];

  readonly form = this.fb.group({
    title: this.fb.control('', Validators.required),
    type: this.fb.control<EventType>('meeting'),
    status: this.fb.control<EventStatus>('scheduled'),
    starts_at: this.fb.control('', Validators.required),
    ends_at: this.fb.control(''),
    is_all_day: this.fb.control(false),
    location: this.fb.control(''),
    description: this.fb.control(''),
  });

  constructor() {
    effect(() => {
      const item = this.event();
      if (item) {
        if (this.lastLoadedId() === item.id) {
          return;
        }
        this.lastLoadedId.set(item.id);
        this.form.reset({
          title: item.title,
          type: item.type,
          status: item.status,
          starts_at: this.toInputDateTime(item.starts_at),
          ends_at: item.ends_at ? this.toInputDateTime(item.ends_at) : '',
          is_all_day: item.is_all_day,
          location: item.location ?? '',
          description: item.description ?? '',
        });
        return;
      }

      this.lastLoadedId.set(null);
      this.resetForm();

      const initial = this.initialData();
      if (initial) {
        this.form.patchValue({
          title: initial.title ?? '',
          type: initial.type ?? 'meeting',
          status: initial.status ?? 'scheduled',
          starts_at: initial.starts_at ? this.toInputDateTime(initial.starts_at) : '',
          ends_at: initial.ends_at ? this.toInputDateTime(initial.ends_at) : '',
          is_all_day: initial.is_all_day ?? false,
          location: initial.location ?? '',
          description: initial.description ?? '',
        });
      }
    });
  }

  submit(): void {
    if (this.form.invalid || this.isSaving()) {
      this.form.markAllAsTouched();
      return;
    }

    const payload = this.form.getRawValue();
    const editing = this.event();
    this.isSaving.set(true);

    const request = editing
      ? this.eventService.update(String(editing.id), payload)
      : this.eventService.create(payload);

    request.pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (response) => {
        this.isSaving.set(false);
        this.resetForm();
        this.saved.emit(response);
      },
      error: () => {
        this.isSaving.set(false);
      },
    });
  }

  cancel(): void {
    this.cancelled.emit();
  }

  private resetForm(): void {
    this.form.reset({
      title: '',
      type: 'meeting',
      status: 'scheduled',
      starts_at: '',
      ends_at: '',
      is_all_day: false,
      location: '',
      description: '',
    });
  }

  private toInputDateTime(value: string | Date | undefined): string {
    if (!value) return '';
    const date = typeof value === 'string' ? new Date(value) : value;
    if (Number.isNaN(date.getTime())) return '';
    const pad = (num: number): string => String(num).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
  }
}
