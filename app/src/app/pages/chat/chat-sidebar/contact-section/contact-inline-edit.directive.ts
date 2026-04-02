import { Directive, ElementRef, EventEmitter, HostListener, Output, inject } from '@angular/core';

/**
 * Diretiva para edicao inline com salvamento via blur ou Enter.
 *
 * @remarks
 * Responsavel por encapsular o comportamento de foco, blur e teclas
 * para inputs usados na secao de contato do sidebar.
 *
 * @example
 * ```html
 * <input appContactInlineEdit (saveInline)="onSave($event)" />
 * ```
 */
@Directive({
  selector: '[appContactInlineEdit]',
  standalone: true,
})
export class ContactInlineEditDirective {
  @Output() readonly saveInline = new EventEmitter<string>();
  @Output() readonly editStart = new EventEmitter<void>();
  @Output() readonly editEnd = new EventEmitter<void>();
  @Output() readonly editCancel = new EventEmitter<void>();

  private readonly elementRef = inject<ElementRef<HTMLInputElement>>(ElementRef);
  private originalValue = '';

  @HostListener('focus')
  onFocus(): void {
    this.originalValue = this.elementRef.nativeElement.value;
    this.editStart.emit();
  }

  @HostListener('blur')
  onBlur(): void {
    this.editEnd.emit();
    this.emitSaveIfChanged();
  }

  @HostListener('keydown.enter', ['$event'])
  onEnter(event: Event): void {
    event.preventDefault();
    this.emitSaveIfChanged();
    this.elementRef.nativeElement.blur();
  }

  @HostListener('keydown.escape', ['$event'])
  onEscape(event: Event): void {
    event.preventDefault();
    this.elementRef.nativeElement.value = this.originalValue;
    this.elementRef.nativeElement.dispatchEvent(new Event('input'));
    this.elementRef.nativeElement.blur();
    this.editCancel.emit();
  }

  private emitSaveIfChanged(): void {
    const value = this.elementRef.nativeElement.value;
    if (value !== this.originalValue) {
      this.saveInline.emit(value);
    }
  }
}
