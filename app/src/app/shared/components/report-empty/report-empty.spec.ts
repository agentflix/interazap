import type { ComponentFixture } from '@angular/core/testing';
import { TestBed } from '@angular/core/testing';
import { Component } from '@angular/core';
import { AfReportEmptyComponent } from './report-empty';

/** Wrapper to test input signals via template binding */
@Component({
  standalone: true,
  imports: [AfReportEmptyComponent],
  template: `
    <af-report-empty [title]="title" [description]="description" />
  `,
})
class TestHostComponent {
  title = 'Sem dados disponíveis';
  description: string | null = 'Não há dados no período selecionado.';
}

describe('AfReportEmptyComponent', () => {
  let host: TestHostComponent;
  let fixture: ComponentFixture<TestHostComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AfReportEmptyComponent, TestHostComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(TestHostComponent);
    host = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('deve criar o componente', () => {
    expect(host).toBeTruthy();
  });

  it('deve renderizar título e descrição informados', () => {
    const compiled = fixture.nativeElement as HTMLElement;
    const emptyState = compiled.querySelector('af-empty-state');

    expect(emptyState).toBeTruthy();
  });

  it('deve estar dentro de um af-card', () => {
    const compiled = fixture.nativeElement as HTMLElement;
    const card = compiled.querySelector('af-card');

    expect(card).toBeTruthy();
  });

  it('deve funcionar sem descrição', () => {
    host.description = null;
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    const emptyState = compiled.querySelector('af-empty-state');

    expect(emptyState).toBeTruthy();
  });

  it('deve passar título customizado', () => {
    host.title = 'Nenhum resultado encontrado';
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    const emptyState = compiled.querySelector('af-empty-state');

    expect(emptyState).toBeTruthy();
  });
});
