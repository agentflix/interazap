import { describe, it, expect, beforeEach } from 'vitest';
import { Component } from '@angular/core';
import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { By } from '@angular/platform-browser';
import { ContactInlineEditDirective } from './contact-inline-edit.directive';

@Component({
  standalone: true,
  template:
    '<input type="text" appContactInlineEdit (saveInline)="onSave($event)" (editCancel)="onCancel()" />',
  imports: [ContactInlineEditDirective],
})
class HostComponent {
  savedValue: string | null = null;
  cancelled = false;

  onSave(value: string): void {
    this.savedValue = value;
  }

  onCancel(): void {
    this.cancelled = true;
  }
}

describe('ContactInlineEditDirective', (): void => {
  let fixture: ComponentFixture<HostComponent>;
  let component: HostComponent;

  beforeEach((): void => {
    TestBed.configureTestingModule({
      imports: [HostComponent],
    });

    fixture = TestBed.createComponent(HostComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('emits save on blur when value changes', (): void => {
    const input = fixture.debugElement.query(By.css('input')).nativeElement as HTMLInputElement;
    input.dispatchEvent(new Event('focus'));
    input.value = 'Novo valor';
    input.dispatchEvent(new Event('input'));
    input.dispatchEvent(new Event('blur'));

    expect(component.savedValue).toBe('Novo valor');
  });

  it('emits save on Enter key', (): void => {
    const input = fixture.debugElement.query(By.css('input')).nativeElement as HTMLInputElement;
    input.dispatchEvent(new Event('focus'));
    input.value = 'Salvar';
    input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter' }));

    expect(component.savedValue).toBe('Salvar');
  });

  it('reverts value and emits cancel on Escape', (): void => {
    const input = fixture.debugElement.query(By.css('input')).nativeElement as HTMLInputElement;
    input.value = 'Original';
    input.dispatchEvent(new Event('focus'));
    input.value = 'Alterado';
    input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));

    expect(component.cancelled).toBe(true);
  });
});
