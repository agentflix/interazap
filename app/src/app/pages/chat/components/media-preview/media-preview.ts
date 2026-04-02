import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  EventEmitter,
  Output,
  computed,
  effect,
  inject,
  input,
  signal,
} from '@angular/core';
import { DecimalPipe } from '@angular/common';
import { NgIcon, provideIcons } from '@ng-icons/core';
import {
  lucideAlertCircle,
  lucideCheck,
  lucideFile,
  lucideImage,
  lucideLoader2,
  lucideMic,
  lucidePlus,
  lucideSend,
  lucideVideo,
  lucideX,
} from '@ng-icons/lucide';
import { type MediaPreviewItem } from 'src/app/shared/models/media-preview.model';
import { IconButtonComponent } from 'src/app/shared/components/buttons';
import { FormControl } from '@angular/forms';
import { TextInputComponent } from 'src/app/shared/components/inputs';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';

@Component({
  selector: 'app-media-preview',
  standalone: true,
  imports: [NgIcon, DecimalPipe, IconButtonComponent, TextInputComponent],
  templateUrl: './media-preview.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  viewProviders: [
    provideIcons({
      lucideX,
      lucidePlus,
      lucideSend,
      lucideCheck,
      lucideLoader2,
      lucideFile,
      lucideImage,
      lucideVideo,
      lucideMic,
      lucideAlertCircle,
    }),
/**
 * Media preview component for the Chat module.
 * @selector app-media-preview
 */
  ],
})
export class MediaPreviewComponent {
  private readonly destroyRef = inject(DestroyRef);
  readonly open = input(false);
  readonly items = input<MediaPreviewItem[]>([]);
  readonly selectedId = input<string | null>(null);
  readonly progress = input(0);
  readonly isSending = input(false);

  @Output() readonly closed = new EventEmitter<void>();
  @Output() readonly itemSelected = new EventEmitter<string>();
  @Output() readonly remove = new EventEmitter<string>();
  @Output() readonly addFiles = new EventEmitter<FileList>();
  @Output() readonly updateCaption = new EventEmitter<{ id: string; caption: string }>();
  @Output() readonly send = new EventEmitter<void>();

  readonly fileInputId = signal(`media-preview-${Math.random().toString(36).slice(2)}`);

  readonly selectedItem = computed(() => {
    const items = this.items();
    const id = this.selectedId();
    if (!items.length) return null;
    if (!id) return items[0];
    return items.find((item) => item.id === id) ?? items[0];
  });

  readonly progressPercent = computed(() => Math.round(this.progress() * 100));

  readonly captionControl = new FormControl<string>('', { nonNullable: true });

  constructor() {
    this.captionControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((value) => this.onCaptionChange(value));

    effect(() => {
      const caption = this.selectedItem()?.caption ?? '';
      this.captionControl.setValue(caption, { emitEvent: false });
    });
  }

  triggerFileInput(): void {
    const input = document.getElementById(this.fileInputId()) as HTMLInputElement | null;
    input?.click();
  }

  onAddFiles(event: Event): void {
    const input = event.target as HTMLInputElement;
    if (!input.files || input.files.length === 0) return;
    this.addFiles.emit(input.files);
    input.value = '';
  }

  onCaptionChange(value: string): void {
    const selected = this.selectedItem();
    if (!selected) return;
    this.updateCaption.emit({ id: selected.id, caption: value });
  }
}
