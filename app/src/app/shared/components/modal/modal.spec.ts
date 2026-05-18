import type { ComponentFixture } from '@angular/core/testing';
import { TestBed } from '@angular/core/testing';
import { By } from '@angular/platform-browser';

import { AfModalComponent } from './modal';

describe('AfModalComponent', () => {
  let fixture: ComponentFixture<AfModalComponent>;
  let component: AfModalComponent;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AfModalComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(AfModalComponent);
    component = fixture.componentInstance;
  });

  it('deve renderizar container com z-index acima do topbar quando aberto', () => {
    fixture.componentRef.setInput('open', true);
    fixture.detectChanges();

    const dialogContainer = fixture.debugElement.query(
      By.css('div.fixed.inset-0[role="dialog"]'),
    )?.nativeElement as HTMLElement | undefined;

    expect(dialogContainer).toBeTruthy();
    expect(dialogContainer?.className).toContain('z-[100]');
  });

  it('não deve renderizar o modal quando open é false', () => {
    fixture.componentRef.setInput('open', false);
    fixture.detectChanges();

    const dialogContainer = fixture.debugElement.query(
      By.css('div.fixed.inset-0[role="dialog"]'),
    );

    expect(dialogContainer).toBeFalsy();
  });

  it('deve emitir closed ao clicar no botão de fechar', () => {
    fixture.componentRef.setInput('open', true);
    fixture.componentRef.setInput('title', 'Teste');
    fixture.detectChanges();

    let closedEmitted = false;
    component.closed.subscribe(() => {
      closedEmitted = true;
    });

    const closeBtn = fixture.debugElement.query(
      By.css('button[aria-label="Fechar"]'),
    )?.nativeElement as HTMLElement | undefined;

    expect(closeBtn).toBeTruthy();
    closeBtn?.click();

    expect(closedEmitted).toBe(true);
  });

  it('deve emitir closed ao clicar no backdrop quando closeOnBackdrop é true', () => {
    fixture.componentRef.setInput('open', true);
    fixture.componentRef.setInput('closeOnBackdrop', true);
    fixture.detectChanges();

    let closedEmitted = false;
    component.closed.subscribe(() => {
      closedEmitted = true;
    });

    const overlay = fixture.debugElement.query(
      By.css('div.absolute.inset-0[role="button"]'),
    )?.nativeElement as HTMLElement | undefined;

    expect(overlay).toBeTruthy();
    overlay?.click();

    expect(closedEmitted).toBe(true);
  });

  it('não deve emitir closed ao clicar no backdrop quando closeOnBackdrop é false', () => {
    fixture.componentRef.setInput('open', true);
    fixture.componentRef.setInput('closeOnBackdrop', false);
    fixture.detectChanges();

    let closedEmitted = false;
    component.closed.subscribe(() => {
      closedEmitted = true;
    });

    const overlay = fixture.debugElement.query(
      By.css('div.absolute.inset-0[role="button"]'),
    )?.nativeElement as HTMLElement | undefined;

    overlay?.click();

    expect(closedEmitted).toBe(false);
  });

  it('deve emitir closed ao pressionar Escape quando aberto', () => {
    fixture.componentRef.setInput('open', true);
    fixture.detectChanges();

    let closedEmitted = false;
    component.closed.subscribe(() => {
      closedEmitted = true;
    });

    // Trigger Escape via the host listener
    const hostEl = fixture.nativeElement as HTMLElement;
    const event = new KeyboardEvent('keydown', { key: 'Escape' });
    document.dispatchEvent(event);

    expect(closedEmitted).toBe(true);
  });

  it('não deve emitir closed ao pressionar Escape quando fechado', () => {
    fixture.componentRef.setInput('open', false);
    fixture.detectChanges();

    let closedEmitted = false;
    component.closed.subscribe(() => {
      closedEmitted = true;
    });

    const event = new KeyboardEvent('keydown', { key: 'Escape' });
    document.dispatchEvent(event);

    expect(closedEmitted).toBe(false);
  });

  it('não deve mostrar botão de fechar quando showClose é false', () => {
    fixture.componentRef.setInput('open', true);
    fixture.componentRef.setInput('showClose', false);
    fixture.detectChanges();

    const closeBtn = fixture.debugElement.query(
      By.css('button[aria-label="Fechar"]'),
    );

    expect(closeBtn).toBeFalsy();
  });

  it('deve aplicar a classe de tamanho correta para cada size', () => {
    const sizes: ('sm' | 'md' | 'lg' | 'xl')[] = ['sm', 'md', 'lg', 'xl'];
    const expectedClasses: Record<string, string> = {
      sm: 'max-w-sm',
      md: 'max-w-lg',
      lg: 'max-w-2xl',
      xl: 'max-w-4xl',
    };

    for (const size of sizes) {
      fixture.componentRef.setInput('open', true);
      fixture.componentRef.setInput('size', size);
      fixture.detectChanges();

      const panel = fixture.debugElement.query(
        By.css('div.relative.z-10'),
      )?.nativeElement as HTMLElement | undefined;

      expect(panel?.className).toContain(expectedClasses[size]);
    }
  });

  it('deve exibir o título quando fornecido', () => {
    fixture.componentRef.setInput('open', true);
    fixture.componentRef.setInput('title', 'Título de teste');
    fixture.detectChanges();

    const titleEl = fixture.debugElement.query(
      By.css('h2'),
    )?.nativeElement as HTMLElement | undefined;

    expect(titleEl).toBeTruthy();
    expect(titleEl?.textContent).toContain('Título de teste');
  });

  it('deve suportar isOpen como alias legado para open', () => {
    fixture.componentRef.setInput('isOpen', true);
    fixture.detectChanges();

    const dialogContainer = fixture.debugElement.query(
      By.css('div.fixed.inset-0[role="dialog"]'),
    );

    expect(dialogContainer).toBeTruthy();
  });
});
