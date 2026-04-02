import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  EventEmitter,
  Input,
  Output,
  computed,
  inject,
  signal,
} from '@angular/core';
import { FormControl, ReactiveFormsModule, Validators } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { NgIcon, provideIcons } from '@ng-icons/core';
import { lucideX } from '@ng-icons/lucide';
import { toast } from 'ngx-sonner';
import { TagService } from 'src/app/core/services/tag.service';
import { ModalComponent } from 'src/app/shared/components/modal/modal';
import { type ContactTag, type TagChip, buildTagChip, buildTagColor } from './tag-chip';
import { TextInputComponent } from 'src/app/shared/components/text-input/text-input';
import { ButtonComponent } from 'src/app/shared/components/button/button';

/**
 * Modal para gerenciar tags de um contato.
 *
 * @remarks
 * Permite adicionar, remover e criar tags para um contato.
 * Suporta busca de tags existentes e criacao inline de novas tags.
 *
 * @example
 * ```html
 * <app-tags-modal
 *   [isOpen]="isOpen"
 *   [contactId]="contactId"
 *   [appliedTags]="tags"
 *   (closed)="onClosed()"
 *   (tagsUpdated)="onTagsUpdated($event)" />
 * ```
 */
@Component({
  selector: 'app-tags-modal',
  standalone: true,
  imports: [ReactiveFormsModule, NgIcon, ModalComponent, TextInputComponent, ButtonComponent],
  viewProviders: [provideIcons({ lucideX })],
  templateUrl: './tags-modal.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TagsModalComponent {
  private readonly tagService = inject(TagService);
  private readonly destroyRef = inject(DestroyRef);

  readonly isOpenSignal = signal(false);
  readonly isLoading = signal(false);
  readonly isSaving = signal(false);
  readonly availableTags = signal<TagChip[]>([]);
  readonly appliedTagsSignal = signal<ContactTag[]>([]);

  readonly tagInput = new FormControl('', [Validators.minLength(2)]);

  readonly suggestedTags = computed(() => {
    const appliedIds = new Set(this.appliedTagsSignal().map((tag) => tag.id));
    return this.availableTags().filter((tag) => tag.id && !appliedIds.has(tag.id));
  });

  @Input() set isOpen(value: boolean) {
    this.isOpenSignal.set(value);
    if (value) {
      this.loadTags();
    }
  }

  @Input() contactId: string | number | null = null;

  @Input() set appliedTags(value: ContactTag[]) {
    this.appliedTagsSignal.set(value ?? []);
  }

  @Output() readonly closed = new EventEmitter<void>();
  @Output() readonly tagsUpdated = new EventEmitter<ContactTag[]>();

  close(): void {
    this.closed.emit();
  }

  onInputKeydown(event: KeyboardEvent): void {
    if (event.key !== 'Enter' && event.key !== ' ') return;
    event.preventDefault();
    this.createTagFromInput();
  }

  addTag(tag: TagChip): void {
    if (!this.contactId || !tag.id) return;
    if (this.appliedTagsSignal().some((existing) => existing.id === tag.id)) return;

    this.isSaving.set(true);
    this.tagService
      .attachToContact(String(this.contactId), tag.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          const updated = [...this.appliedTagsSignal(), { id: tag.id!, name: tag.name }];
          this.appliedTagsSignal.set(updated);
          this.tagsUpdated.emit(updated);
          this.isSaving.set(false);
          toast.success('Tag adicionada.');
        },
        error: () => {
          this.isSaving.set(false);
          toast.error('Não foi possível adicionar a tag.');
        },
      });
  }

  removeTag(tag: ContactTag): void {
    if (!this.contactId) return;

    this.isSaving.set(true);
    this.tagService
      .detachFromContact(String(this.contactId), tag.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          const updated = this.appliedTagsSignal().filter((item) => item.id !== tag.id);
          this.appliedTagsSignal.set(updated);
          this.tagsUpdated.emit(updated);
          this.isSaving.set(false);
          toast.success('Tag removida.');
        },
        error: () => {
          this.isSaving.set(false);
          toast.error('Não foi possível remover a tag.');
        },
      });
  }

  private createTagFromInput(): void {
    const raw = String(this.tagInput.value ?? '').trim();
    if (!raw || this.tagInput.invalid) return;

    const payload = {
      name: raw,
      color: buildTagColor(raw),
      is_active: true,
    };

    this.isSaving.set(true);
    this.tagService
      .create(payload)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          const created = response.data;
          const chip = buildTagChip(created);
          this.availableTags.set([...this.availableTags(), chip]);
          this.tagInput.reset();
          this.isSaving.set(false);
          if (created.id) {
            this.addTag(chip);
          }
        },
        error: () => {
          this.isSaving.set(false);
          toast.error('Não foi possível criar a tag.');
        },
      });
  }

  private loadTags(): void {
    this.isLoading.set(true);
    this.tagService
      .all()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          const tags = response.data.tags.map(buildTagChip);
          this.availableTags.set(tags);
          this.isLoading.set(false);
        },
        error: () => {
          this.isLoading.set(false);
          toast.error('Não foi possível carregar as tags.');
        },
      });
  }
}

export type { ContactTag } from './tag-chip';
