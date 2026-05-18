import type { ComponentFixture } from '@angular/core/testing';
import { TestBed } from '@angular/core/testing';
import { Component } from '@angular/core';
import { AfReportErrorComponent } from './report-error';

/** Wrapper to test input signals and output via template binding */
@Component({
  standalone: true,
  imports: [AfReportErrorComponent],
  template: `
    <af-report-error
      [title]="title"
      [message]="message"
      (retry)="onRetry()"
    />
  `,
})
class TestHostComponent {
  title = 'Erro ao carregar dados';
  message = 'Não foi possível carregar o relatório.';
  retryCount = 0;

  onRetry(): void {
    this.retryCount++;
  }
}

describe('AfReportErrorComponent', () => {
  let host: TestHostComponent;
  let fixture: ComponentFixture<TestHostComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AfReportErrorComponent, TestHostComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(TestHostComponent);
    host = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('deve criar o componente', () => {
    expect(host).toBeTruthy();
  });

  it('deve renderizar título e mensagem padrão', () => {
    const compiled = fixture.nativeElement as HTMLElement;
    const heading = compiled.querySelector('h3');
    const paragraph = compiled.querySelector('p');

    expect(heading?.textContent?.trim()).toBe('Erro ao carregar dados');
    expect(paragraph?.textContent?.trim()).toBe('Não foi possível carregar o relatório.');
  });

  it('deve renderizar título e mensagem customizados', () => {
    host.title = 'Falha na conexão';
    host.message = 'Verifique sua internet e tente novamente.';
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    const heading = compiled.querySelector('h3');
    const paragraph = compiled.querySelector('p');

    expect(heading?.textContent?.trim()).toBe('Falha na conexão');
    expect(paragraph?.textContent?.trim()).toBe('Verifique sua internet e tente novamente.');
  });

  it('deve emitir evento retry ao clicar no botão', () => {
    const compiled = fixture.nativeElement as HTMLElement;
    const button = compiled.querySelector('af-button');

    expect(host.retryCount).toBe(0);
    button?.dispatchEvent(new Event('clicked'));
    expect(host.retryCount).toBe(1);
  });

  it('deve conter botão com texto "Tentar novamente"', () => {
    const compiled = fixture.nativeElement as HTMLElement;
    const button = compiled.querySelector('af-button');

    expect(button?.textContent?.trim()).toContain('Tentar novamente');
  });

  it('deve estar dentro de um af-card', () => {
    const compiled = fixture.nativeElement as HTMLElement;
    const card = compiled.querySelector('af-card');

    expect(card).toBeTruthy();
  });
});
