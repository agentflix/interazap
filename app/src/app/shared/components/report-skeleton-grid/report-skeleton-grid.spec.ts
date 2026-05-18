import type { ComponentFixture } from '@angular/core/testing';
import { TestBed } from '@angular/core/testing';
import { Component } from '@angular/core';
import { AfReportSkeletonGridComponent } from './report-skeleton-grid';

/** Wrapper to test input signals via template binding */
@Component({
  standalone: true,
  imports: [AfReportSkeletonGridComponent],
  template: `
    <af-report-skeleton-grid [count]="count" [smCols]="smCols" [lgCols]="lgCols" />
  `,
})
class TestHostComponent {
  count = 4;
  smCols: number | null = null;
  lgCols: number | null = null;
}

describe('AfReportSkeletonGridComponent', () => {
  let host: TestHostComponent;
  let fixture: ComponentFixture<TestHostComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AfReportSkeletonGridComponent, TestHostComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(TestHostComponent);
    host = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('deve criar o componente', () => {
    expect(host).toBeTruthy();
  });

  it('deve renderizar o número correto de skeleton cards', () => {
    const compiled = fixture.nativeElement as HTMLElement;
    const cards = compiled.querySelectorAll('af-card');

    expect(cards.length).toBe(4);
  });

  it('deve renderizar 6 cards quando count=6', () => {
    host.count = 6;
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    const cards = compiled.querySelectorAll('af-card');

    expect(cards.length).toBe(6);
  });

  it('deve renderizar 1 card quando count=1', () => {
    host.count = 1;
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    const cards = compiled.querySelectorAll('af-card');

    expect(cards.length).toBe(1);
  });

  it('deve usar grid responsivo com classes Tailwind', () => {
    const compiled = fixture.nativeElement as HTMLElement;
    const grid = compiled.querySelector('div.grid');

    expect(grid?.className).toContain('grid-cols-1');
    expect(grid?.className).toContain('sm:grid-cols-2');
    expect(grid?.className).toContain('lg:grid-cols-4');
  });

  it('deve respeitar smCols customizado', () => {
    host.count = 6;
    host.smCols = 2;
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    const grid = compiled.querySelector('div.grid');

    expect(grid?.className).toContain('sm:grid-cols-2');
  });

  it('deve respeitar lgCols customizado', () => {
    host.count = 6;
    host.lgCols = 3;
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    const grid = compiled.querySelector('div.grid');

    expect(grid?.className).toContain('lg:grid-cols-3');
  });

  it('deve renderizar skeletons dentro de cada card', () => {
    const compiled = fixture.nativeElement as HTMLElement;
    const cards = compiled.querySelectorAll('af-card');

    cards.forEach((card) => {
      const skeleton = card.querySelector('af-skeleton');
      expect(skeleton).toBeTruthy();
    });
  });

  it('deve ter gap-4 no grid container', () => {
    const compiled = fixture.nativeElement as HTMLElement;
    const grid = compiled.querySelector('div.grid');

    expect(grid?.className).toContain('gap-4');
  });
});
