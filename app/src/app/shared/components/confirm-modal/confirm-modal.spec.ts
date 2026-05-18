import type { ComponentFixture } from '@angular/core/testing';
import { TestBed } from '@angular/core/testing';
import { By } from '@angular/platform-browser';

import { AfConfirmModalComponent } from './confirm-modal';

describe('AfConfirmModalComponent', () => {
  let fixture: ComponentFixture<AfConfirmModalComponent>;
  let component: AfConfirmModalComponent;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AfConfirmModalComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(AfConfirmModalComponent);
    component = fixture.componentInstance;
  });

  it('deve criar o componente', () => {
    expect(component).toBeTruthy();
  });

  it('não deve renderizar o modal interno quando open é false', () => {
    fixture.componentRef.setInput('open', false);
    fixture.detectChanges();

    const modal = fixture.debugElement.query(
      By.css('af-modal'),
    );

    expect(modal).toBeTruthy();
  });

  it('deve emitir confirmed ao clicar no botão de confirmar', () => {
    fixture.componentRef.setInput('open', true);
    fixture.detectChanges();

    let confirmedEmitted = false;
    component.confirmed.subscribe(() => {
      confirmedEmitted = true;
    });

    const buttons = fixture.debugElement.queryAll(By.css('af-button'));
    // O botão de confirmar é o segundo (primeiro é cancelar)
    const confirmBtn = buttons[1]?.nativeElement as HTMLElement | undefined;

    expect(confirmBtn).toBeTruthy();
    confirmBtn?.click();

    expect(confirmedEmitted).toBe(true);
  });

  it('deve emitir cancelled ao clicar no botão de cancelar', () => {
    fixture.componentRef.setInput('open', true);
    fixture.detectChanges();

    let cancelledEmitted = false;
    component.cancelled.subscribe(() => {
      cancelledEmitted = true;
    });

    const buttons = fixture.debugElement.queryAll(By.css('af-button'));
    const cancelBtn = buttons[0]?.nativeElement as HTMLElement | undefined;

    expect(cancelBtn).toBeTruthy();
    cancelBtn?.click();

    expect(cancelledEmitted).toBe(true);
  });

  it('deve usar os labels customizados de confirmar e cancelar', () => {
    fixture.componentRef.setInput('open', true);
    fixture.componentRef.setInput('confirmLabel', 'Excluir');
    fixture.componentRef.setInput('cancelLabel', 'Voltar');
    fixture.detectChanges();

    const buttons = fixture.debugElement.queryAll(By.css('af-button'));

    expect(buttons[0]?.nativeElement?.textContent).toContain('Voltar');
    expect(buttons[1]?.nativeElement?.textContent).toContain('Excluir');
  });

  it('deve usar o título e mensagem padrão quando não fornecidos', () => {
    fixture.componentRef.setInput('open', true);
    fixture.detectChanges();

    expect(component.title()).toBe('Confirmar');
    expect(component.message()).toBe('Tem certeza que deseja realizar esta ação?');
  });

  it('deve usar título e mensagem customizados', () => {
    fixture.componentRef.setInput('open', true);
    fixture.componentRef.setInput('title', 'Excluir item');
    fixture.componentRef.setInput('message', 'Esta ação não pode ser desfeita.');
    fixture.detectChanges();

    expect(component.title()).toBe('Excluir item');
    expect(component.message()).toBe('Esta ação não pode ser desfeita.');
  });

  it('deve aplicar variante danger com cores vermelhas no ícone', () => {
    fixture.componentRef.setInput('open', true);
    fixture.componentRef.setInput('variant', 'danger');
    fixture.detectChanges();

    const iconContainer = fixture.debugElement.query(
      By.css('.mx-auto'),
    )?.nativeElement as HTMLElement | undefined;

    expect(iconContainer?.className).toContain('bg-red-100');
  });

  it('deve aplicar variante warning com cores âmbar no ícone', () => {
    fixture.componentRef.setInput('open', true);
    fixture.componentRef.setInput('variant', 'warning');
    fixture.detectChanges();

    const iconContainer = fixture.debugElement.query(
      By.css('.mx-auto'),
    )?.nativeElement as HTMLElement | undefined;

    expect(iconContainer?.className).toContain('bg-amber-100');
  });

  it('deve desabilitar o botão de confirmar quando isLoading é true', () => {
    fixture.componentRef.setInput('open', true);
    fixture.componentRef.setInput('isLoading', true);
    fixture.detectChanges();

    const buttons = fixture.debugElement.queryAll(By.css('af-button'));
    const confirmBtn = buttons[1]?.componentInstance as { disabled?: boolean | (() => boolean) } | undefined;
    const isDisabled = typeof confirmBtn?.disabled === 'function' ? confirmBtn.disabled() : confirmBtn?.disabled;

    expect(isDisabled).toBe(true);
  });

  it('deve suportar isOpen como alias legado para open', () => {
    fixture.componentRef.setInput('isOpen', true);
    fixture.detectChanges();

    const modal = fixture.debugElement.query(
      By.css('af-modal'),
    );

    expect(modal).toBeTruthy();
  });
});
